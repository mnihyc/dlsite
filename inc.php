<?php
    /* The default value is recommended except tokens and passwords. */
    /* NOTICE: '/assets/', '/view', '/down', '/manage' are reserved and
    can NOT be used as file/dirname (or otherwise they will be inaccessible). */
    /* NOTICE: file/dirname can NOT contain the character '|'. */
    
    /* Set local envirenment to Chinese */
    setlocale(LC_ALL,'zh_CN.UTF8');

    /* NOTICE: Do NOT use '/' as the last character */
    /* Directory of these php files(/ as webroot) */
    /* Leaving this as default value is highly recommended */
    define('ROOT_DIR','');
    
    /* NOTICE: Do NOT use '/' as the last character */
    /* Directory of the main folder(/ as webroot) */
    define('FILE_DIR','/dl');
    
    /* NOTICE: The following two tokens must be DIFFERENT. */
    /* The token of password verification (keep it SECRET) */
    define('DEF_PASS','123456');
    
    /* The token of downloading files (keep it SECRET) */
    define('DEF_DOWN','789101112');
    
    /* Buffer size of downloading */
    define('READ_BS',1024*64);
    
    /* Configuration file to save */
    define('CONFIG_FILE','/db.sqlite');
    
    /* Show the content of index.html if exists */
    define('SHOWDEFPAGE',true);
    
    /* Prefer legacy ways of processing parameters */
    /* Legacy: /[filepath]?[para] */
    /* New: /[method]?[para] which reduces URI length greatly */
    define('OLDSTYLE_PATH',false);
    
    /* Whether include visible file/dir path in URI */
    /* Available when OLDSTYLE_PATH is set to false */
    define('INCLUDE_VISPATH',true);
    
    /* Whether support new ways of processing parameters */
    /* Available when OLDSTYLE_PATH is set to true */
    define('SUPPORT_NEWPATH',true);
    
    /* Redirect to whether 'view' or 'down' when a direct path is accessed */
    /* Available when OLDSTYLE_PATH is set to true */
    define('REDIRECT_OLDPATH','view');
    
    /* Displayed when an error occurs */
    define('ADMIN_EMAIL','YOUR_EMAIL');
    
    /* Encrypted password of the management page (keep it SECRET) */
    /* The way to compute: md5(md5(PSWD).'+'.sha1(PSWD)) */
    /* Default value 7f6d747029adeefe073804e34b089020 means blank password */
    define('MANAGE_PASSWORD','7f6d747029adeefe073804e34b089020');
    
    /* Define the order of download methods */
    $down_order=array(2,1,3);
    
    /* Supported download methods */
    /* NOTICE: Please do NOT change it to cause confusion */
    $down_str=array(1=>'this site',2=>'OneDrive',3=>'Google Drive');

    // ^---------------------------------------
    
    $db=NULL;
    $o_header=false;
    $page_title='File Download Service';

    /* Check if the two files/directories are the same */
    function samefd($p1,$p2)
    {
        $p1.='/';$p2.='/';
        filterpath($p1);filterpath($p2);
        return ($p1===$p2);
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
            $str.=('/'.rawurlencode($val));
        return $str;
    }
    
    /* urlencode() a directory for API calls */
    function encodeapidir($path)
    {
        return encodedir(str_replace(':','：',$path));
    }
    
    /* Check the password of the management page */
    function checkmanagepassword()
    {
?>
<div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">A <strong>password</strong> verification is required to access this page. <br></p>
<?php
        $passvld=true;
        if(isset($_POST['manage']))
        {
            $_SESSION['manage']=gethashedpass($_POST['manage']);
            $_SESSION['expired']=time();
        }
        
        if(!isset($_SESSION['expired']))
            $passvld=false;
        else if(abs(time()-$_SESSION['expired'])>=3600*24)
        {
            $passvld=false;
            echo '<p class="lead text-center">Verification <span style="color: red;"><strong>expired</strong></span>.</p>';
        }
        else
        {
            if($_SESSION['manage']===MANAGE_PASSWORD)
            {
                $passvld=true;
                echo '<p class="lead text-center">Verification <span style="color: green;"><strong>passed</strong></span>.</p>';
                
            }
            else
            {
                $passvld=false;
                echo '<p class="lead text-center">Verification <span style="color: red;"><strong>failed</strong></span>.</p>';
            }
        }
        if(!$passvld)
        {
?>
<form action="#" method="post">
    <div class="table-responsive">
        <table class="table table-borderless">
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
    function checkpassword(&$inpassver,&$inpasswd,$passwd,$opath,$isdd=false)
    {
?>
<div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">This file/directory is protected with <strong>password</strong>. <br></p>
<?php
        if($inpassver)
        {
            if(isset($_POST['pass']))
            {
                /* Do not save (inputed) cleartext password under any circumstances */
                $inpasswd=gethashedpass($_POST['pass']);
                ob_end_clean();
                /* Generate view/down link by $isdd (direct link) */
                header('Location: '.getviewlink($opath,true,$inpasswd),TRUE,301);
                die();
            }
            $inpasswd=encrypt($inpasswd,'D',DEF_PASS);
            if($inpasswd!==FALSE && strpos($inpasswd,'|')!==FALSE)
            {
                $arr=explode('|',$inpasswd);
                $passtime=intval($arr[0]);
                if(abs(time()-$passtime)>3600*24)
                {
                    $inpassver=true;
                    echo '<p class="lead text-center">Verification <span style="color: red;"><strong>expired</strong></span>.</p>';
                }
                else
                {
                    $inpasswd=$arr[1];
                    if(($inpasswd!==$passwd && $inpasswd!==MANAGE_PASSWORD) || !samefd($arr[2],$opath))
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
<form action="<?php echo (OLDSTYLE_PATH?'':'view?p=').encodedir(ROOT_DIR.$opath); ?>" method="post">
    <div class="table-responsive">
        <table class="table table-borderless">
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
        if(samefd($name,__DIR__.'/inc.php') || samefd($name,__DIR__.'/main.php')
            || samefd($name,__DIR__.'/api.php') || samefd($name,__DIR__.CONFIG_FILE))
            return true;
        return false;
    }
    
    class Database extends SQLite3
    {
        function __construct()
        {
            $dbfile=__DIR__.CONFIG_FILE;
            $this->open($dbfile);
            $this->busyTimeout(30000);
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
        $dbfile=__DIR__.CONFIG_FILE;
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
    
    /* Get a refresh token of api.php */
    function getrefreshtoken($name,$type)
    {
        global $db;
        $arr=$db->queryarr("SELECT VALUE FROM CONFIG WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_refresh_token'");
        if(empty($arr))
        {
            $db->execwf("INSERT INTO CONFIG (NAME,TYPE,VALUE) VALUES ('{$db->escapeString($name)}','{$db->escapeString($type)}_refresh_token','')");
            return '';
        }
        return $arr[0]['VALUE'];
    }
    
    /* Update a refresh token of api.php */
    function updaterefreshtoken($name,$type,$token)
    {
        global $db;
        $db->execwf('BEGIN EXCLUSIVE TRANSACTION');
        $db->execwf("UPDATE CONFIG SET VALUE='{$db->escapeString($token)}' WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_refresh_token'");
        $db->execwf('END TRANSACTION');
    }
    
    /* Get an access token of api.php */
    function getaccesstoken($name,$type)
    {
        global $db;
        $arr=$db->queryarr("SELECT VALUE FROM CONFIG WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_access_token'");
        if(empty($arr))
            $db->execwf("INSERT INTO CONFIG (NAME,TYPE,VALUE) VALUES ('{$db->escapeString($name)}','{$db->escapeString($type)}_access_token','')");
        $arr1=$db->queryarr("SELECT VALUE FROM CONFIG WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_expiry_time'");
        if(empty($arr1))
            $db->execwf("INSERT INTO CONFIG (NAME,TYPE,VALUE) VALUES ('{$db->escapeString($name)}','{$db->escapeString($type)}_expiry_time','0')");
        if(empty($arr) || empty($arr1) || intval($arr1[0]['VALUE'])-10 < time())
            return false;
        return $arr[0]['VALUE'];
    }
    
    /* Update an access token of api.php */
    function updateaccesstoken($name,$type,$time,$token)
    {
        global $db;
        $db->execwf('BEGIN EXCLUSIVE TRANSACTION');
        $db->execwf("UPDATE CONFIG SET VALUE='{$db->escapeString($token)}' WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_access_token'");
        $db->execwf("UPDATE CONFIG SET VALUE='{$db->escapeString(strval($time))}' WHERE NAME='{$db->escapeString($name)}' AND TYPE='{$db->escapeString($type)}_expiry_time'");
        $db->execwf('END TRANSACTION');
    }
    
    /* Get an array of TYPEs<->VALUEs in specific NAME */
    function getdbtypes($name)
    {
        global $db;
        $arr=$db->queryarr("SELECT TYPE,VALUE FROM CONFIG WHERE NAME='{$db->escapeString($name)}'");
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
            if(isset($passwdarr['cur'.$type]))
            {
                $passver=true;
                $passwd=$passwdarr['cur'.$type];
            }
            if(strtolower($passwdarr['nocur'.$type])==='yes')
                $passver=$checkm=false;
        }
        /* Be careful of cross reference */
        if(isset($passwd) && !empty($passwd) && $passver)
            $passwd=gethashedpass($passwd);
        if(!$passver && $checkm)
            $passwd=getdirpass(dirname($opath),$type,true);
        if(!isset($passwd) || $passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        return ($passver==false ? FALSE : $passwd);
    }
    
    /* Read the password/... in all sub-directory */
    function getdirpass($tpath,$type='dirpass',$ispar=false)
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
                if($pathfirst && strtolower($passwdarr['nocur'.$type])==='yes')
                    $passver=$cont=false;
                if((!$pathfirst || $ispar) && isset($passwdarr['sub'.$type]))
                {
                    $passver=true;
                    $passwd=$passwdarr['sub'.$type];
                }
                if((!$pathfirst || $ispar) && strtolower($passwdarr['subno'.$type])==='yes')
                    $passver=$cont=false;
                if($passver || !$cont)
                    break;
            }
            $tpath=dirname($tpath);
            $pathfirst=false;
        }
        return ($passver==false ? FALSE : gethashedpass($passwd));
    }

    function getdownlink($path,$passver,$inpasswd,$type)
    {
        return (OLDSTYLE_PATH?encodedir(ROOT_DIR.$path):'down').'?'.(INCLUDE_VISPATH?'p='.encodedir($path).'&':'').rawurlencode(encrypt($type.'|'.($passver ? $inpasswd : '').'|'.strval(time()).'|'.$path,'E',DEF_DOWN));
    }
    
    function getviewlink($path,$passver,$inpasswd)
    {
        return (OLDSTYLE_PATH?encodedir(ROOT_DIR.$path):'view').($passver ? '?'.(INCLUDE_VISPATH?'p='.encodedir($path).'&':'').rawurlencode(encrypt(strval(time()).'|'.$inpasswd.'|'.$path,'E',DEF_PASS)) : '?p='.encodedir($path));
    }

    /* Return the hashed password */
    function gethashedpass($pass)
    {
        return md5(md5($pass).'+'.sha1($pass));
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
        
        $type=' bytes';
        if($size>=1000*1000*1000)
        {
            $size/=1000*1000*1000;
            $type=' GB';
        }
        else if($size>=1000*1000)
        {
            $size/=1000*1000;
            $type=' MB';
        }
        else if($size>=1000)
        {
            $size/=1000;
            $type=' KB';
        }
        
        if(floatval($size)==intval($size))
            $str=sprintf("%d",$size);
        else
            $str=sprintf("%1\$.2f",$size);
        return $str.$type;
    }
    
    /* Get the modified time of a file/dir */
    function getmodtime($path)
    {
        return filemtime($path);
    }
    
    /* Format a timestamp */
    function formatdate($time)
    {
        return strftime('%Y/%m/%d %H:%M:%S',$time);
    }

    /* Output an error message */
    function diemsg($msg='An unknown error occurs.')
    {
        global $o_header;
        if(!$o_header)
            htmlmsg();
        echo '<h1 class="text-center" style="margin:46px;">'.$msg.'</h1>';
        echo '<p class="lead text-right" style="padding:25px;margin:0px;">Need support? Please contact the server administrator at '.ADMIN_EMAIL.' ......</p>';
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
            global $page_title;
?>
<!DOCTYPE html>
<!-- Code by mnihyc -->
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="pragma" content="no-cache" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?php echo ROOT_DIR ?>/assets/bootstrap/css/bootstrap.min.css">
        <title><?php echo $page_title ?></title>
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
        <style>
            .table-borderless thead tr th, .table-borderless tbody tr td {
                border: none;
            }
            .text-larger {
                font-size: 18px;
                line-height: 1;
            }
        </style>
    
        <?php }
        else
        {?>
    
    </body>
</html>
        <?php }
    }
?>
