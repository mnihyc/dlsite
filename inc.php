<?php
    /* Set local envirenment to Chinese */
    setlocale(LC_ALL,'zh_CN.UTF8');

    /* Directory of these two .php files(/ as webroot) */
    define('ROOT_DIR','');
    
    /* Directory of the main folder(/ as webroot) */
    define('FILE_DIR','/dl');
    
    /* The token of password verification */
    define('DEF_PASS','123456');
    
    /* The token of downloading files */
    define('DEF_DOWN','789101112');
    
    /* Buffer size of downloading */
    define('READ_BS',1024*64);
    
    /* Configuration file to save */
    define('CONFIG_FILE','/db.sqlite');
    
    /* Show the content of index.html if exists */
    define('SHOWDEFPAGE',true);
    
    /* Encrypted password of the management page */
    /* The way to compute: md5(md5(PSWD).'+'.sha1(PSWD)) */
    /* Default value 7f6d747029adeefe073804e34b089020 means blank password */
    define('MANAGE_PASSWORD','7f6d747029adeefe073804e34b089020');
    
    /* Abandoned */
    //define('DIRPASS_NAME','/.pass');

    $db=NULL;
    $o_header=false;

    /* Check if the two files/directories are the same */
    function samefd($p1,$p2)
    {
        filterpath($p1);filterpath($p2);
        return (dirname($p1.'/1.b')===dirname($p2.'/2.c'));
    }

    /* Execute necessary scripts or show html pages */
    function commandefiles($path)
    {
        if(substr($path,-4)==='.php')
        {
            require $path;
            die();
        }
        if(substr($path,-5)==='.html' || substr($path,-3)==='.js' || substr($path,-4)==='.css'
            || substr($path,-4)==='.htm')
        {
            echo file_get_contents($path);
            die();
        }
    }
    
    /* Execute default scripts or pages */
    function commandedir($path)
    {
        if(file_exists($path.'/index.php'))
        {
            require $path.'/index.php';
            die();
        }
        if(file_exists($path.'/index.html'))
        {
            echo file_get_contents($path.'/index.html');
            die();
        }
    }

    /* Filter a path */
    function filterpath(&$path)
    {
        $count=1;
        while($count)
            $path=str_replace('//','/',$path,$count);
    }

    /* urlencode() a directory */
    function encodedir($path)
    {
        $str='';
        $arr=explode('/',$path);
        unset($arr[0]);
        foreach($arr as $key => $val)
            $str.=('/'.urlencode($val));
        return $str;
    }
    
    /* Check the password of the management page */
    function checkmanagepassword()
    {
?>
<div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">A <strong>password</strong> verification is required to access this page. <br></p>
<?php
        $passvld=true;
        if(isset($_POST['manage']))
        {
            $_SESSION['manage']=$_POST['manage'];
            $_SESSION['expired']=time();
        }
        
        if(md5(md5($_SESSION['manage']).'+'.sha1($_SESSION['manage']))!==MANAGE_PASSWORD && abs(time()-$_SESSION['expired'])<3600)
        {
            $passvld=false;
            echo '<p class="lead text-center">Verification <span style="color: red;"><strong>expired</strong></span>.</p>';
        }
        else if(abs(time()-$_SESSION['expired'])>=3600)
        {
            $passvld=false;
            echo '<p class="lead text-center">Verification <span style="color: red;"><strong>failed</strong></span>.</p>';
        }
        else
        {
            $passvld=true;
            echo '<p class="lead text-center">Verification <span style="color: green;"><strong>passed</strong></span>.</p>';
        }
        if(!$passvld)
        {
?>
<form action="#" method="post">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:432px;"><input class="form-control d-table ml-auto text-center" type="password" name="manage" autofocus="" autocomplete="off" style="width:441px;"></th>
                    <th style="width:191px;"><button class="btn btn-dark" type="submit">Verify</button></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</form>
<?php
        }
?>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
<?php
        return $passvld;
    }
    
    /* Check for the verification */
    function checkpassword(&$inpassver,&$inpasswd,$passwd,$opath)
    {
?>
<div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">This file/directory is protected with <strong>password</strong>. <br></p>
<?php
        if($inpassver)
        {
            if(substr($inpasswd,0,5)==='pass=')
            {
                $inpasswd=substr($inpasswd,5);
                ob_end_clean();
                header('Location: '.encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt(strval(time()).'|'.$inpasswd.'|'.$opath,'E',DEF_PASS)),TRUE,301);
                die();
            }
            $inpasswd=encrypt($inpasswd,'D',DEF_PASS);
            if($inpasswd!==FALSE && strpos($inpasswd,'|')!==FALSE)
            {
                $arr=explode('|',$inpasswd);
                $passtime=intval($arr[0]);
                if(abs(time()-$passtime)>=3600)
                {
                    $inpassver=true;
                    echo '<p class="lead text-center">Verification <span style="color: red;"><strong>expired</strong></span>.</p>';
                }
                else
                {
                    $inpasswd=$arr[1];
                    if(($inpasswd!==$passwd && md5(md5($arr[1]).'+'.sha1($arr[1]))!==MANAGE_PASSWORD) || !samefd($arr[2],$opath))
                    {
                        $inpassver=true;
                        echo '<p class="lead text-center">Verification <span style="color: red;"><strong>failed</strong></span>.</p>';
                    }
                    else
                    {
                        $inpassver=false;
                        echo '<p class="lead text-center">Verification <span style="color: green;"><strong>passed</strong></span>.</p>';
                    }
                }
            }
            else
            {
                $inpassver=true;
                echo '<p class="lead text-center">Verification <span style="color: red;"><strong>failed</strong></span> unexpectedly.</p>';
            }
        }
        else
            $inpassver=true;
        
        if($inpassver)
        {
?>
<form action="<?php echo encodedir(ROOT_DIR.$opath); ?>">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:432px;"><input class="form-control d-table ml-auto text-center" type="password" name="pass" autofocus="" autocomplete="off" style="width:441px;"></th>
                    <th style="width:191px;"><button class="btn btn-dark" type="submit">Verify</button></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</form>
<?php
        }
?>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
<?php
    }

    /* Check whether a file is accessible */
    function invalidfilename($name)
    {
        if(basename($name)==='.htaccess')
            return true;
        /* Self-protection */
        if(samefd($name,dirname(__FILE__).'/inc.php') || samefd($name,dirname(__FILE__).'/view.php')
            || samefd($name,dirname(__FILE__).CONFIG_FILE))
            return true;
        return false;
    }
    
    class Database extends SQLite3
    {
        function __construct()
        {
            $dbfile=dirname(__FILE__).CONFIG_FILE;
            $this->open($dbfile);
        }
        public function execwf($sql)
        {
            $ret=$this->exec($sql);
            if(!$ret)
                diemsg($sql.'<br />SQL Halt: '.$this->lastErrorMsg());
        }
        public function queryarr($sql)
        {
            $ret=$this->query($sql);
            if(!$ret)
                diemsg($sql.'<br />SQL Halt: '.$this->lastErrorMsg());
            $arr=array();
            while($row=$ret->fetchArray(SQLITE3_ASSOC))
                array_push($arr,$row);
            return $arr;
        }
    }
    
    function opendb()
    {
        $dbfile=dirname(__FILE__).CONFIG_FILE;
        if(!file_exists($dbfile))
            $init=true;
        global $db;
        $db=new Database();
        if(!$db)
            diemsg('Unable to open the database set in the configuration file!');
        if($init)
        {
            $sql =<<<EOF
                CREATE TABLE CONFIG
                (
                NAME           NTEXT   NOT NULL,
                TYPE           NTEXT   NOT NULL,
                VALUE          NTEXT   NOT NULL,
                PRIMARY KEY (NAME,TYPE));
EOF;
            $db->execwf($sql);
        }
    }
    
    /* Get an array of TYPEs<->VALUEs in specific NAME */
    function getdbtypes($name)
    {
        global $db;
        $arr=$db->queryarr('SELECT TYPE,VALUE FROM CONFIG WHERE NAME=\''.$db->escapeString($name).'\'');
        $ret=array();
        foreach($arr as $key => $val)
            $ret[$val['TYPE']]=$val['VALUE'];
        return $ret;
    }
    
    /* Read the password/... of a file */
    function getfilepass($opath,$type='filepass')
    {
        $passwdarr=getdbtypes($opath);
        $passver=false;$passwd='';$checkm=true;
        if(count($passwdarr))
        {
            $passver=true;
            $passwd=$passwdarr[$type];
            if(!isset($passwd))
                $passver=false;
            if(strtolower($passwdarr['no'.$type])==='yes')
                $passver=$checkm=false;
            else if(isset($passwdarr['cur'.$type]))
            {
                $passver=true;
                $passwd=urldecode($passwdarr['cur'.$type]);
            }
            else
                $passwd=urldecode($passwd);
        }
        if(!$passver && $checkm)
            $passwd=getdirpass(dirname($opath),$type);
        if(!isset($passwd) || $passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        return ($passver==false ? FALSE : $passwd);
    }
    
    /* Read the password/... in all sub-directory */
    function getdirpass($tpath,$type='dirpass')
    {
        $first=false;$pathfirst=true;
        while(!empty($tpath) && !$first)
        {
            if($tpath==='/')
                $first=true;
            if(substr($tpath,-1,1)!=='/')
                $tpath.='/';
            $passwdarr=getdbtypes($tpath);
            if(count($passwdarr))
            {
                $passver=$cont=true;
                $passwd=$passwdarr[$type];
                if(!isset($passwd))
                    $passver=false;
                if(strtolower($passwdarr['no'.$type])==='yes')
                    $passver=$cont=false;
                if($pathfirst && isset($passwdarr['cur'.$type]))
                {
                    $passver=true;
                    $passwd=$passwdarr['cur'.$type];
                }
                if($passver || !$cont)
                    break;
            }
            $tpath=dirname($tpath);
            $pathfirst=false;
        }
        return ($passver==false ? FALSE : urldecode($passwd));
    }

    /* Encrypt the string */
    function encrypt($string,$operation,$key='')
    {
        $key=md5($key);
        $key_length=strlen($key);
        $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
        $string_length=strlen($string);
        $rndkey=$box=array();
        $result='';
        for($i=0;$i<=255;$i++)
        {
            $rndkey[$i]=ord($key[$i%$key_length]);
            $box[$i]=$i;
        }
        for($j=$i=0;$i<256;$i++)
        {
            $j=($j+$box[$i]+$rndkey[$i])%256;
            $tmp=$box[$i];
            $box[$i]=$box[$j];
            $box[$j]=$tmp;
        }
        for($a=$j=$i=0;$i<$string_length;$i++)
        {
            $a=($a+1)%256;
            $j=($j+$box[$a])%256;
            $tmp=$box[$a];
            $box[$a]=$box[$j];
            $box[$j]=$tmp;
            $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
        }
        if($operation=='D')
            if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8))
                return substr($result,8);
            else
                return false;
        else
            return str_replace('=','',base64_encode($result));
    }

    /* Get the size of a file */
    function getfilesize($path)
    {
        $size=0.0;
        $size=filesize($path);
        
        $type=' Bytes';
        if($size>=1024*1024*1024)
        {
            $size/=1024*1024*1024;
            $type=' GB';
        }
        else if($size>=1024*1024)
        {
            $size/=1024*1024;
            $type=' MB';
        }
        else if($size>=1024)
        {
            $size/=1024;
            $type=' KB';
        }
        
        if(floatval($size)==intval($size))
            $str=sprintf("%d",$size);
        else
            $str=sprintf("%1\$.2f",$size);
        return $str.$type;
    }

    /* Output an error message */
    function diemsg($msg='An unknown error occurs.')
    {
        global $o_header;
        if(!$o_header)
            htmlmsg();
        echo '<h1 class="text-center" style="margin:46px;">'.$msg.'</h1>';
        echo '<p class="lead text-right" style="padding:25px;margin:0px;">Need support? Please contant the server administrator at rmnihyc@gmail.com ......</p>';
        htmlmsg(false);
        if($db)
            $db->close();
        die();
    }
    
    /* Output the html header/footer */
    function htmlmsg($header=true)
    {
        if($header)
        {
            header("Pragma: no-cache");
            global $o_header;
            $o_header=true;
?>
<!DOCTYPE html>
<!-- Code by mnihyc -->
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="pragma" content="no-cache" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?php echo ROOT_DIR ?>/assets/bootstrap/css/bootstrap.min.css">
        <title>File Download Service</title>
    </head>
    
    <body>
        <script src="<?php echo ROOT_DIR ?>/assets/js/jquery.min.js"></script>
        <script src="<?php echo ROOT_DIR ?>/assets/bootstrap/js/bootstrap.min.js"></script>
        <script>
            window.onload=function()
            {
                $("#itsql").focus();
            }
        </script>
    
        <?php }
        else
        {?>
    
    </body>
</html>
        <?php }
    }
?>
