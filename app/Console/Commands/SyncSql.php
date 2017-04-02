<?php
/**
 * Created by PhpStorm.
 * User: miko
 * Date: 17/3/10
 * Time: 09:26
 */

namespace App\Console\Commands;

use App\DripEmailer;
use Illuminate\Console\Command;
use Illuminate\Database\Connectors;


class SyncSql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * @translator laravelacademy.org
     */
    protected $signature = 'sql:sync {game} {action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync sql to db';

    /**
     * Create a new command instance.
     *
     * @param  DripEmailer  $drip
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $game = $this->argument('game');
        $action   = $this->argument('action');

        if ($action == 'ok') {
            define("ACTION", "OK");
        } else {
            define("ACTION", "NO");
        }
        $message = "\n".date('Y-m-d H:i:s') . " ========================================= $game\n";

        $dbconf = config('database.connections.mysql');

        /*************数据库连接*******************/
        global $link;
        if (!$link = mysqli_connect($dbconf['host'], $dbconf['username'], $dbconf['password'])) {
            exit('connect error:' . mysqli_error($link) . "\n");
        }
        if (!mysqli_select_db($link, $dbconf['database'])) {
            exit('select db error:' . mysqli_error($link) . "\n");
        }

        mysqli_query($link, "set names " . $dbconf['charset']);

        /*****************处理字符串，去掉一些注释的代码**********************/
        $sql = file_get_contents(dirname(dirname(dirname(dirname(__FILE__)))).'/docs/'.$game.'.sql');

        // 去除如/***/的注释
        $sql = preg_replace("[(/\*)+.+(\*/;\s*)]", '', $sql);
        // 去除如--类的注释
        $sql = preg_replace("(--.*?\n)", '', $sql);


        /*****************处理字符串，去掉一些注释的代码**********************/

        preg_match_all("/CREATE\s+TABLE\s+IF NOT EXISTS.+?(.+?)\s*\((.+?)\)\s*(ENGINE|TYPE)\s*\=(.+?;)/is", $sql, $matches);
        $newtables = empty($matches[1])?array():$matches[1];
        $newsqls = empty($matches[0])?array():$matches[0];

        $execSqlInfo = new execSqlInfo();

        $totalNum = count($newtables);
        for ($num = 0; $num < $totalNum; $num++) {
            $newcols = $this->getcolumn($newsqls[$num]);
            $newtable = $newtables[$num];
            $oldtable = $newtable;

            $checksql = "SHOW CREATE TABLE {$newtable}";

            $query = mysqli_query($link, $checksql);
            if (!$query) {
                $usql = $newsqls[$num];
                $usql = str_replace($oldtable, $newtable, $usql);
                $this->execQuery($execSqlInfo, $usql);
            } else {
                $value = mysqli_fetch_array($query);

                // 判断注释
                if ($comment = $this->checkTableComment($newsqls[$num], $value['Create Table'])) {
                    $usql = "ALTER TABLE ".$newtable." COMMENT =  '{$comment}'";
                    $this->execQuery($execSqlInfo, $usql);
                }

                $oldcols = $this->getcolumn($value['Create Table']);
                $updates = array();
                $allfileds =array_keys($newcols);
                foreach ($newcols as $key => $value) {
                    if($key == 'PRIMARY') {
                        if(!isset($oldcols[$key]) || $value != $oldcols[$key]) {
                            if(!empty($oldcols[$key])) {
                                $usql = "RENAME TABLE ".$newtable." TO ".$newtable . '_bak';
                                $this->execQuery($execSqlInfo, $usql);
                            }
                            $updates[] = "ADD PRIMARY KEY $value";
                        }
                    } elseif ($key == 'KEY') {
                        foreach ($value as $subkey => $subvalue) {
                            if(!empty($oldcols['KEY'][$subkey])) {
                                if($subvalue != $oldcols['KEY'][$subkey]) {
                                    $updates[] = "DROP INDEX `$subkey`";
                                    $updates[] = "ADD INDEX `$subkey` $subvalue";
                                }
                            } else {
                                $updates[] = "ADD INDEX `$subkey` $subvalue";
                            }
                        }
                    } elseif ($key == 'UNIQUE') {
                        foreach ($value as $subkey => $subvalue) {
                            if(!empty($oldcols['UNIQUE'][$subkey])) {
                                if($subvalue != $oldcols['UNIQUE'][$subkey]) {
                                    $updates[] = "DROP INDEX `$subkey`";
                                    $updates[] = "ADD UNIQUE INDEX `$subkey` $subvalue";
                                }
                            } else {
                                $usql = "ALTER TABLE  ".$newtable." DROP INDEX `$subkey`";
                                $this->execQuery($execSqlInfo, $usql);
                                $updates[] = "ADD UNIQUE INDEX `$subkey` $subvalue";
                            }
                        }
                    } else {
                        if(!empty($oldcols[$key])) {
                            if(strtolower($value) != strtolower($oldcols[$key])) {
                                $updates[] = "CHANGE `$key` `$key` $value";
                            }
                        } else {
                            $i = array_search($key, $allfileds);
                            $fieldposition = $i > 0 ? 'AFTER `'.$allfileds[$i-1].'`' : 'FIRST';
                            $updates[] = "ADD `$key` $value $fieldposition";
                        }
                    }
                }
                if ($updates) {
                    $usql = "ALTER TABLE ".$newtable." ".implode(', ', $updates);

                    $this->execQuery($execSqlInfo, $usql);
                } else {
                    $this->checkColumnDiff($execSqlInfo, $newcols, $oldcols);
                }
            }
        }
        $message .= $execSqlInfo->formatMessage();
        echo $message;
    }

    public function execQuery($execSqlInfo, $sql) {

        $res = true;
        global $link;
        if (ACTION == 'OK') {
            $res = mysqli_query($link, $sql);
        }
        if (!$res) {
            $debug = debug_backtrace();
            $execSqlInfo->setMsg('line ' . $debug[0]['line'] . ' : ' . 'sql wrong:' . $sql . '  ' . mysqli_error($link));
        } else {
            // 记录
            $execSqlInfo->setSql($sql);
        }

    }
    /**
     *
     * 检索两个数组的键值顺序是否一致，若不一致列出具体的信息
     */
    function checkColumnDiff($execSqlInfo, $newCols, $oldCols) {

        if (array_keys($newCols) == array_keys($oldCols)) {
            return false;
        }
        if (count($newCols) != count($oldCols)) {
            return false;
        }
        $size = count($newCols);

        for ($i=0; $i < $size; $i++) {
            $newCol = key($newCols);
            $oldCol = key($oldCols);

            if (!empty($newCol) && !in_array($newCol, array('KEY', 'INDEX', 'UNIQUE', 'PRIMARY'))
                && $newCol != $oldCol) {
                $execSqlInfo->setMsg("字段顺序不正确: 第" . ($i+1) . "个字段 sql中字段为 {$newCol} 数据库中字段为 {$oldCol}" );
            }
            next($newCols);
            next($oldCols);
        }

    }

    function checkTableComment($newSql, $oldSql) {


        if (!$newSql || !$oldSql) {
            return false;
        }

        $tmp1 = explode("\n",$newSql);
        $tmp2 = explode("\n",$oldSql);
        // 获取最后一行
        $newlastSql = array_pop($tmp1);
        $oldlastSql = array_pop($tmp2);
        $newComment = '';
        $oldComment = '';
        if (preg_match("/COMMENT\='(.*)'/is", $newlastSql, $matchs))
            $newComment = $matchs[1];

        if (!$newComment)
            return false;

        if (preg_match("/COMMENT\='(.*)'/is", $oldlastSql, $matchs))
            $oldComment = $matchs[1];


        if ($newComment == $oldComment)
            return false;

        return $newComment;
    }



    function remakesql($value) {
        $value = trim(preg_replace("/\s+/", ' ', $value));
        $value = str_replace(array('`',', ', ' ,', '( ' ,' )', 'mediumtext'), array('', ',', ',','(',')','text'), $value);
        return $value;
    }

    function getcolumn($creatsql) {

        preg_match("/\((.+)\)\s*(ENGINE|TYPE)\s*\=/is", $creatsql, $matchs);
        if (!isset($matchs[1])) {
            return [];
        }

        $cols = explode("\n", $matchs[1]);
        $newcols = array();
        foreach ($cols as $value) {
            $value = trim($value);
            if(empty($value)) continue;
            $value = $this->remakesql($value);
            if(substr($value, -1) == ',') $value = substr($value, 0, -1);

            $vs = explode(' ', $value);
            $cname = $vs[0];

            if($cname == 'KEY' || $cname == 'INDEX' || $cname == 'UNIQUE') {

                $name_length = strlen($cname);
                if($cname == 'UNIQUE') $name_length = $name_length + 4;

                $subvalue = trim(substr($value, $name_length));
                $subvs = explode(' ', $subvalue);
                $subcname = $subvs[0];
                $newcols[$cname][$subcname] = trim(substr($value, ($name_length+2+strlen($subcname))));

            }  elseif($cname == 'PRIMARY') {

                $newcols[$cname] = trim(substr($value, 11));

            }  else {

                $newcols[$cname] = trim(substr($value, strlen($cname)));
            }
        }
        return $newcols;
    }
}


class execSqlInfo {

    public $msgList = array();
    public $excsqlList = array();

    function setMsg($message) {
        $this->msgList[] = $message;
    }

    function setSql($querysql) {
        $this->excsqlList[] = $querysql;
    }

    function formatMessage() {
        $showMessage = " ";

        if (!$this->excsqlList && !$this->msgList) {
            $showMessage .= "nothing to do!\n";
        } else {
            if($this->msgList) {
                $showMessage .= "error message:\n ";
                foreach ($this->msgList as $v) {
                    $showMessage .= $v . "\n
*******************************************************\n\n";
                }
            }
            $showMessage .= "exec sql :\n";
            foreach ($this->excsqlList as $v) {
                $showMessage .= $v . "\n
////////////////////////////////////////////////////////\n\n";
            }
        }
        return $showMessage;
    }
}