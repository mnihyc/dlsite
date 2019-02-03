<?php
    define(IS_IN_PHP,true);
    include 'inc.php';
    
    /* Get the path as well as password(if exists) */
    $opath=urldecode($_SERVER['REQUEST_URI']);
    $opath=str_replace(ROOT_DIR,'',$opath,$cnt);
    $inpassver=false;$inpasswd='';
    if(strpos($opath,'?')!==FALSE)
    {
        $inpassver=true;
        $vpos=strpos($opath,'?');
        $inpasswd=substr($opath,$vpos+1);
        $oinpasswd=$inpasswd;
        $opath=substr($opath,0,$vpos);
    }
    filterpath($opath);
    $path=dirname(__FILE__).FILE_DIR.$opath;
    if(!empty(ROOT_DIR) && $cnt==0)
        diemsg();
    if(invalidfilename($path))
        diemsg('Access denied.');
    if(strpos($path,'../')!==FALSE || strpos($path,'/..')!==FALSE)
        diemsg('Invalid filename!');
    $path=str_replace('./','',$path);
    
    /* Different operations for the file and directory */
    if(is_file($path))
    {
        /* Specially command for executable files */
        commandefiles($path);
        
        $passwd=getfilepass($opath);
        if($passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        
        /* No password verification and a direct link requirement */
        if(!$passver && $inpasswd==='direct')
        {
            header('Location: '.encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt('on'.'|'.($passver ? $inpasswd : '').'|'.strval(time()).'|'.$opath,'E',DEF_DOWN)),TRUE,301);
            die();
        }
        
        /* This is a downloading link */
        $downs=encrypt($inpasswd,'D',DEF_DOWN);
        if($downs!==FALSE && strpos($downs,'|')!==FALSE)
        {
            $arr=explode('|',$downs);
            if($arr[0]!=='dn' && $arr[0]!=='on')
                diemsg();
            $passtime=intval($arr[2]);
            
            /* Check whether it's valid */
            if(abs(time()-$passtime)<=3600*24 && samefd($arr[3],$opath) && ($passver==false || ($passver==true && $arr[1]===$passwd)))
            {
                if(abs(time()-$passtime)>=3600)
                {
                    header('Location: '.encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt(trim($arr[0]).'|'.($inpassver ? $inpasswd : '').'|'.strval(time()).'|'.$opath,'E',DEF_DOWN)),TRUE,301);
                    die();
                }
                
                $size=filesize($path);
                $type=mime_content_type($path);
                $fname=basename($path);
                $r_f=0;$r_t=$size-1;
                
                /* Support the range header simply */
                if(isset($_SERVER['HTTP_RANGE']))
                {
                    header("{$_SERVER['SERVER_PROTOCOL']} 206 Partial Content");
                    $range = explode('-',str_replace('=','-',$_SERVER['HTTP_RANGE']));
                    $r_f=intval(trim($range[1]));
                    if(intval(trim($range[2]))>0)
                    $r_t=intval(trim($range[2]));
                    if($r_t<$r_f || $r_t>=$size)
                        die;
                    header("Content-Range: bytes {$r_f}-{$r_t}/{$size}");
                }
                header("Content-Length: ".strval(abs($r_t-$r_f)+1));
                header("Content-Type: {$type}");
                header("Accept-Ranges: bytes");
                
                /* Download or open */
                if($arr[0]==='dn')
                {
                    header("Content-Disposition: attachment; filename=\"{$fname}\"");
                    //header("Connection: Close");
                }
                else
                {
                    header("Content-Disposition: inline; filename=\"{$fname}\"");
                    //header("Connection: Keep-Alive");
                }
                header('Content-Transfer-Encoding: binary');
                
                /* Transfer data */
                $fp=fopen('php://output','wb');
                $fpl=fopen($path,'rb');
                fseek($fpl,$r_f,SEEK_SET);
                $bytes=0;$size=abs($r_t-$r_f)+1;
                while($bytes<=$size)
                {
                    $nread=READ_BS;
                    if($nread>abs($size-$bytes)+1)
                        $nread=abs($size-$bytes)+1;
                    $buf=fread($fpl,$nread);
                    if($buf===FALSE)
                        break;
                    if(fwrite($fp,$buf)===FALSE)
                        break;
                    $bytes+=$nread;
                }
                fclose($fpl);
                fclose($fp);
                die();
            }
        }
        
        /* Show the main page */
        ob_start();
        htmlmsg();
        echo '<h1 class="text-center">Accessing to \''.htmlentities(basename($opath)).'\' ......</h1>';
        
?>
<div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">File path: &nbsp; &nbsp; &nbsp; &nbsp; <?php echo htmlentities($opath); ?></p>
                            <p class="lead text-center">File size: &nbsp; &nbsp; &nbsp; &nbsp; <?php echo getfilesize($path); ?></p>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
<?php
        if($passver)
            checkpassword($inpassver,$inpasswd,$passwd,$opath);
        
        /* Have passed the verification, so show the downloading link */
        if(!$passver || ($passver && !$inpassver))
        {
?>
<div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:485px;">
                        <div class="d-table ml-auto"><a class="btn btn-dark" role="button" href="<?php echo encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt('on'.'|'.($passver ? $inpasswd : '').'|'.strval(time()).'|'.$opath,'E',DEF_DOWN)); ?>" target="_blank">Open</a></div>
                    </th>
                    <th style="width:28px;"><strong>or</strong></th>
                    <th style="width:502px;">
                        <div class="d-table mr-auto"><a class="btn btn-dark" role="button" href="<?php echo encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt('dn'.'|'.($passver ? $inpasswd : '').'|'.strval(time()).'|'.$opath,'E',DEF_DOWN)); ?>" target="_blank">Download</a></div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr></tr>
            </tbody>
        </table>
    </div>
<?php
        }
        
        htmlmsg(false);
    }
    else if(is_dir($path))
    {
        /* Filter the directory */
        if(substr($opath,-1)!=='/')
        {
            $path.='/';
            $opath.='/';
        }
        if(strpos('.',$path))
            diemsg('Yep, some unexpected things happened, you know what I mean('.$opath.').');
        
        $passwd=getdirpass($opath);
        if($passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        
        /* Show the main page */
        ob_start();
        htmlmsg();
        $dna=explode('/',$opath);
        echo '<h1 class="text-center">Directory of \''.htmlentities($dna[count($dna)-2].'/').'\' ......</h1>';
        if($passver)
            checkpassword($inpassver,$inpasswd,$passwd,$opath);
?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="d-table-cell">
                        <div class="container">
                            <p class="lead text-center">Directory path: &nbsp; &nbsp; &nbsp;<?php echo htmlentities($opath); ?></p>
<?php
        $endhtml='</div></th></tr></thead><tbody></tbody></table></div>';
        /* Have passed the verification, so show the elements inside */
        if(!$passver || ($passver && !$inpassver))
        {
            $elecnt=0;
            $outhtml='<div class="table-responsive"><table class="table"><thead><tr></tr></thead><tbody>';
            $file=scandir($path);
            foreach($file as $val)
            {
                /* Filter the filename */
                if($val==='.' || $val==='..' || invalidfilename($val))
                    continue;
                $elecnt++;
                $fopath=$opath.$val;
                $fpath=dirname(__FILE__).FILE_DIR.$fopath;
                $ispd=is_dir($fpath);
                
                /* Construct the html code */
                $outhtml.='<tr><td><p class="text-center">'.htmlentities($ispd ? $val.'/' : $val).'</p></td><td style="text-align:right;width:150px;">'.($ispd ? htmlentities('<DIR>') : getfilesize($fpath)).'</td><td style="width:150px;"><a class="btn btn-dark" role="button" href="'.encodedir($fopath).($passver ? '?'.urlencode(encrypt(strval(time()).'|'.$inpasswd.'|'.$fopath,'E',DEF_PASS)) : '').'" target="_blank" style="width:64px;">Open</a></td></tr>';
            }
            $endhtml='<p class="lead text-center">Total elements: &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; '.strval($elecnt).'</p>'.$endhtml;
            $outhtml.='</tbody></table></div>';
        }
        echo $endhtml.$outhtml;
        htmlmsg(false);
    }
    else
        diemsg('The file/directory doesn\'t exist!');
    
    ob_flush();
?>