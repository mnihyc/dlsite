<?php
    define('IS_IN_PHP',true);
    ini_set('session.cookie_lifetime',3600*24);
    ini_set('session.gc_maxlifetime',3600*24);
    //error_reporting(E_ERROR);
    session_start();
    $db=NULL;
    include_once 'inc.php';
    include_once 'api.php';
    
    /* Get the path as well as password(if exists) */
    $opath=urldecode($_SERVER['REQUEST_URI']);
    $inpassver=false;$inpasswd='';
    if(($vpos=strpos($opath,'?'))!==FALSE)
    {
        $inpassver=true;
        $inpasswd=substr($opath,$vpos+1);
        $oinpasswd=$inpasswd;
        $opath=substr($opath,0,$vpos);
    }
    if(!empty(ROOT_DIR))
    {
        if(substr($opath,0,strlen(ROOT_DIR))!==ROOT_DIR)
            diemsg('Incorrect ROOT_DIR is set');
        $opath=substr($opath,strlen(ROOT_DIR));
    }
    
    /* Adapt to new ways of processing parameters */
    $ddrequest=false;
    if(!OLDSTYLE_PATH || SUPPORT_NEWPATH)
    {
        $ismanage=($opath=='/manage');
        if($opath=='/view' || $opath=='/down' || $opath=='/manage')
        {
            $ropath='';
            if(substr($inpasswd,0,2)=='p=')
            {
                $ropath=substr($inpasswd,2);
                if($ismanage)
                    $inpasswd='manage';
                else
                    $inpassver=isset($_POST['pass']);
                if(($vpos=strpos($ropath,'&'))!==FALSE)
                {
                    $inpasswd=substr($ropath,$vpos+1);
                    $inpassver|=!empty($inpasswd);
                    $ropath=substr($ropath,0,$vpos);
                }
            }
            $vtmp=encrypt($inpasswd,'D',DEF_PASS);
            $dtmp=encrypt($inpasswd,'D',DEF_DOWN);
            if($opath=='/view')
                if($vtmp!==false && strpos($vtmp,'|')!==false)
                {
                    $arr=explode('|',$vtmp);
                    $opath=$arr[2];
                }
                /* Convert from down to view */
                else if($dtmp!==false && strpos($dtmp,'|')!==false)
                {
                    $arr=explode('|',$dtmp);
                    $opath=$arr[3];
                    $inpasswd=encrypt($arr[2].'|'.$arr[1].'|'.$opath,'E',DEF_PASS);
                }
                else if(empty($ropath)) diemsg('Invalid parameter(s)');
                else ;
            else if($opath=='/down')
                if($dtmp!==false && strpos($dtmp,'|')!==false)
                {
                    $arr=explode('|',$dtmp);
                    $opath=$arr[3];
                }
                /* Convert from view to down */
                else if($vtmp!==false && strpos($vtmp,'|')!==false)
                {
                    $arr=explode('|',$vtmp);
                    $opath=$arr[2];
                    //$inpasswd=encrypt('dn'.'|'.$arr[1].'|'.$arr[0].'|'.$opath,'E',DEF_DOWN);
                    /* Unable to 'down' a directory, keeping 'view' */
                    /* Will enter 'down' process only when is_file() */
                    $ddrequest=true;
                }
                else if(empty($ropath)) diemsg('Invalid parameter(s)');
                else $ddrequest=true;
            else if($opath=='/manage')
            {
                if($vtmp!==false && strpos($vtmp,'|')!==false)
                {
                    $arr=explode('|',$vtmp);
                    $opath=$arr[2];
                }
                else if($dtmp!==false && strpos($dtmp,'|')!==false)
                {
                    $arr=explode('|',$dtmp);
                    $opath=$arr[3];
                }
                else if(empty($ropath)) diemsg('Invalid parameter(s)');
                $inpasswd='manage';
            }
            /* NOTICE: Overwrite */
            if(!empty($ropath))
                $opath=$ropath;
        }
        /* This is a legacy path, so redirect */
        else if(!OLDSTYLE_PATH)
            if(empty($inpasswd))
            {
                header('Location: /'.REDIRECT_OLDPATH.'?p='.encodedir($opath),TRUE,301);
                die;
            }
            else if(($vtmp=encrypt($inpasswd,'D',DEF_PASS))!==FALSE)
            {
                header('Location: /view?'.(INCLUDE_VISPATH?'p='.encodedir($opath).'&':'').rawurlencode($inpasswd),TRUE,301);
                die;
            }
            else if(($vtmp=encrypt($inpasswd,'D',DEF_DOWN))!==FALSE)
            {
                header('Location: /down?'.(INCLUDE_VISPATH?'p='.encodedir($opath).'&':'').rawurlencode($inpasswd),TRUE,301);
                die;
            }
            else if($inpasswd==='manage')
                ;
            else diemsg('Invalid parameter(s)');
    }
    
    if(substr($opath,0,1)!=='/')
        $opath='/'.$opath;
    filterpath($opath);
    $path=__DIR__.FILE_DIR.$opath;
    filterpath($path);
    if(strpos($path,'../')!==FALSE || strpos($path,'/..')!==FALSE)
        diemsg('Access denied.');
    $path=str_replace('./','',$path);
    filterpath($path);
    if(invalidfilename($path))
        diemsg('Access denied.');
    
    opendb();
    
    /* Enter the management page */
    if($inpasswd==='manage')
    {
        ob_start();
        htmlmsg();
        if(checkmanagepassword())
        {
            /* Insert a record */
            if(isset($_POST['qi']))
            {
                global $db;
                $db->execwf("INSERT INTO CONFIG (NAME,TYPE,VALUE) VALUES ('{$db->escapeString($_POST['namei'])}','{$db->escapeString($_POST['typei'])}','{$db->escapeString($_POST['valuei'])}')");
            }
            /* Delete a record */
            if(isset($_POST['qd']))
            {
                global $db;
                $db->execwf("DELETE FROM CONFIG WHERE NAME='{$db->escapeString($_POST['named'])}' AND TYPE='{$db->escapeString($_POST['typed'])}'");
            }
            /* Update a record */
            if(isset($_POST['qu']))
            {
                global $db;
                $db->execwf("UPDATE CONFIG SET VALUE='{$db->escapeString($_POST['valueu'])}' WHERE NAME='{$db->escapeString($_POST['nameu'])}' AND TYPE='{$db->escapeString($_POST['typeu'])}'");
            }
            
            if(is_dir(__DIR__.FILE_DIR.$opath) && substr($opath,-1,1)!=='/')
                $opath.='/';
            $qsql="SELECT NAME,TYPE,VALUE FROM CONFIG WHERE NAME LIKE '{$db->escapeString($opath)}%'";
            if(isset($_POST['sql']) && !empty($_POST['sql']))
                $qsql=$_POST['sql'];
            $qnamei=$opath;
            if(isset($_POST['qi']) && isset($_POST['namei']) && !empty($_POST['namei']))
                $qnamei=$_POST['namei'];
            global $db;
            $res=$db->queryarr($qsql);
?>
<div class="table-responsive">
 <table class="table">
  <thead>
   <tr>
    <th class="d-table-cell">
     <div class="container">
      <p class="lead text-center">Number of items: &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <?php echo count($res); ?></p>
      <div class="table-responsive">
      <form action="" method="post">
       <table class="table">
        <thead>
         <tr>
          <th style="width:0px;display:none;"><input type="hidden" name="sql" value="<?php echo htmlentities($qsql); ?>"></th>
          <th style="width:224px;"><input class="form-control d-table ml-auto text-center" type="text" name="namei" autofocus="" autocomplete="off" style="width:577px;" value="<?php echo htmlentities($qnamei); ?>"></th>
          <th style="width:224px;"><input class="form-control d-table ml-auto text-center" type="text" name="typei" autofocus="" autocomplete="off" style="width:135px;" value=""></th>
          <th style="width:224px;"><input class="form-control d-table ml-auto text-center" type="text" name="valuei" autofocus="" autocomplete="off" style="width:175px;" value=""></th>
          <th style="width:191px;"><button class="btn btn-dark" type="submit" name="qi">Insert</button></th>
         </tr>
        </thead>
        <tbody></tbody>
       </table>
      </form>
      </div>
      <div class="table-responsive">
      <form action="" method="post">
       <table class="table">
        <thead>
         <tr>
          <th style="width:224px;"><input id="itsql" class="form-control d-table ml-auto text-center" type="text" name="sql" autofocus="" autocomplete="off" style="width:841px;" value="<?php echo htmlentities($qsql); ?>"></th>
          <th style="width:191px;"><button class="btn btn-dark" type="submit" name="qs">Query</button></th>
         </tr>
        </thead>
        <tbody></tbody>
       </table>
      </form>
      </div>
     </div>
    </th>
   </tr>
  </thead>
  <tbody></tbody>
 </table>
</div>
<div class="table-responsive">
 <table class="table">
  <thead>
   <tr></tr>
  </thead>
  <tbody>
<?php
    foreach($res as $key => $val)
    {
?>
<tr>
 <td>
  <p class="text-center"><?php echo htmlentities($val['NAME']); ?></p>
 </td>
 <td style="text-align:right;">
  <p><?php echo htmlentities($val['TYPE']); ?></p>
 </td>
 <td style="width:255px;">
  <form action="" method="post">
   <input type="hidden" name="sql" value="<?php echo htmlentities($qsql); ?>">
   <input type="hidden" name="named" value="<?php echo htmlentities($val['NAME']); ?>">
   <input type="hidden" name="typed" value="<?php echo htmlentities($val['TYPE']); ?>">
   <button type="button" class="btn btn-dark" data-toggle="collapse" data-target="#tp_<?php echo strval($key); ?>" style="width:80px">Update</button>
 &nbsp; &nbsp; &nbsp;
   <button type="submit" name="qd" class="btn btn-dark" style="width:80px">Delete</button>
  </form>
 </td>
</tr>
<tr class="collapse in" id="tp_<?php echo strval($key); ?>">
 <td colspan="3">
 <form action="" method="post">
  <table class="table">
   <thead>
    <tr>
     <th style="width:0px;display:none;"><input type="hidden" name="sql" value="<?php echo htmlentities($qsql); ?>"></th>
     <th style="width:577px"><input type="hidden" name="nameu" value="<?php echo htmlentities($val['NAME']); ?>"></th>
     <th style="width:577px"><input type="hidden" name="typeu" value="<?php echo htmlentities($val['TYPE']); ?>"></th>
     <th style="width:355px;"><p class="text-right"><?php echo htmlentities($val['VALUE']); ?></p></th>
     <th style="width:243px;"><input class="form-control d-table ml-auto text-center" type="text" name="valueu" autofocus="" autocomplete="off" style="width:175px;" value=""></th>
     <th style="width:191px;"><button class="btn btn-dark" type="submit" name="qu">Submit</button></th>
    </tr>
   </thead>
   <tbody>
    <tr></tr>
   </tbody>
  </table>
 </form>
 </td>
</tr>
<?php
    }
?>
  </tbody>
 </table>
</div>
<?php
        }
        htmlmsg(false);
    }
    
    /* Different operations for the file and directory */
    else if(is_file($path))
    {
        /* Specially command for executable files */
        commandefiles($path);
        
        $passwd=getfilepass($opath);
        if($passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        
        /* No password verification and a direct link is requested */
        $isdd=($inpasswd==='direct' || $ddrequest);
        if(!$passver && $isdd && !$aldd)
        {
            header('Location: '.getdownlink($opath,false,'','on'),TRUE,301);
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
            if(samefd($arr[3],$opath) && ($passver==false || ($passver==true && ($arr[1]===$passwd || $arr[1]===MANAGE_PASSWORD) && abs(time()-$passtime)<=3600*24)))
            {
                if(abs(time()-$passtime)>3600)
                {
                    /* Do not generate a new link */
                    //header('Location: '.encodedir(ROOT_DIR.$opath).'?'.urlencode(encrypt(trim($arr[0]).'|'.($inpassver ? $inpasswd : '').'|'.strval(time()).'|'.$opath,'E',DEF_DOWN)),TRUE,301);
                    //die();
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
                    if($r_f<0 || $r_t<$r_f || $r_t>=$size)
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
        echo '<h1 class="text-center">Accessing \''.htmlentities(basename($opath)).'\' ......</h1>';
        
?>
<div class="table-responsive">
 <table class="table">
  <thead>
   <tr>
    <th class="d-table-cell">
     <div class="container">
      <p class="lead text-center">File path: &nbsp; &nbsp; &nbsp; &nbsp; <?php echo htmlentities($opath); ?></p>
      <p class="lead text-center">File size: &nbsp; &nbsp; &nbsp; &nbsp; <?php echo getfilesize($path); ?></p>
      <?php $exs=getfilepass($opath,'fileextrainfo');if($exs!==FALSE){ ?>
      <p class="lead text-center">Extra information: &nbsp; &nbsp; &nbsp; &nbsp; <?php echo $exs; ?></p>
                            <?php } ?>
     </div>
    </th>
   </tr>
  </thead>
  <tbody></tbody>
 </table>
</div>
<?php
        if($passver)
            checkpassword($inpassver,$inpasswd,$passwd,$opath,$isdd);
        
        /* Have passed the verification, so show the downloading link */
        if(!$passver || ($passver && !$inpassver))
        {
            $dstr=<<<EOF
<div class="table-responsive-sm">
 <table class="table table-striped table-sm"><thead><tr><th>
   <table class="table table-borderless"><thead>
    <tr>
     <th colspan="3">
       <p class="text-center text-larger">Provided by %s</p>
     </th>
    </tr>
    <tr>
     <th style="width:485px;">
      <div class="d-table ml-auto"><a class="btn btn-dark" role="button" href="%s" target="">Open</a></div>
     </th>
     <th style="width:28px;"><strong>or</strong></th>
     <th style="width:502px;">
      <div class="d-table mr-auto"><a class="btn btn-dark" role="button" href="%s" target="">Download</a></div>
     </th>
    </tr>
   </thead>
   <tbody>
    <tr></tr>
   </tbody>
  </table>
  </tr></th></thead><tbody><tr></tr></tbody></table>
</div>
EOF;
            foreach($down_order as $val)
                if($val===1)
                    printf($dstr,$down_str[$val],getdownlink($opath,$passver,$inpasswd,'on'),getdownlink($opath,$passver,$inpasswd,'dn'));
                else if($val===2 && \OneDrive\ENABLED)
                {
                    $odpath=FILE_DIR.$opath;
                    $lk1=\OneDrive\getpreview($odpath);
                    $lk2=\OneDrive\getlink($odpath);
                    if($lk1!==FALSE && $lk2!==FALSE)
                        printf($dstr,$down_str[$val],$lk1,$lk2);
                }
                else if($val===3)
                {
                    
                }
        }
        
        htmlmsg(false);
    }
    else if(is_dir($path))
    {
        if(SHOWDEFPAGE && !$inpassver)
            commandedir($path);
        
        /* Filter the directory */
        if(substr($opath,-1)!=='/')
        {
            $path.='/';
            $opath.='/';
        }
        
        $passwd=getdirpass($opath);
        if($passwd===FALSE)
            $passver=false;
        else
            $passver=true;
        
        /* Show the main page */
        ob_start();
        htmlmsg();
        $dna=explode('/',$opath);
        echo '<h1 class="text-center">Directory of \''.htmlentities($dna[count($dna)-2]).'\' ......</h1>';
        
        $pswdtxt='';
        ob_start( function($str){$GLOBALS['pswdtxt'].=$str; return '';} );
        if($passver)
            checkpassword($inpassver,$inpasswd,$passwd,$opath);
        ob_end_flush();
        /* Pass down password even there's no such restriction */
        $passdown=true;
        if(!$passver)
        {
            $inpasswd=encrypt($inpasswd,'D',DEF_PASS);
            if($inpasswd!==FALSE && strpos($inpasswd,'|')!==FALSE)
            {
                $arr=explode('|',$inpasswd);
                $inpasswd=$arr[1];
            }
            else
                $passdown=false;
        }
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
        $exs=getdirpass($opath,'dirextrainfo');
        if($exs===FALSE)
            $exs='';
        else
            $exs='<p class="lead text-center">Extra information: &nbsp; &nbsp; &nbsp; &nbsp; '.$exs.'</p>';
        /* Have passed the verification, so show the elements inside */
        if(!$passver || ($passver && !$inpassver))
        {
            $elecnt=intval($opath!=='/');
            $outhtml='<div class="table-responsive"><table class="table"><thead><tr></tr></thead><tbody>'."\n";
            if($opath!=='/')
            {
                /* Construct the html code */
                /* There's nothing wrong so I don't need to optimize it */
                $outhtml.='<tr><td><p class="text-center">'.'..'.'</p></td><td style="text-align:right;width:150px;">'.htmlentities('<DIR>').'</td><td style="width:150px;"><a class="btn btn-dark" role="button" href="'.getviewlink(dirname($opath),$passdown,$inpasswd).'" target="" style="width:64px;">Open</a></td></tr>'."\n";
            }
            $file=scandir($path);
            foreach($file as $val)
            {
                /* Filter the filename */
                if($val==='.' || $val==='..' || invalidfilename($val))
                    continue;
                $elecnt++;
                $fopath=$opath.$val;
                $fpath=__DIR__.FILE_DIR.$fopath;
                $ispd=is_dir($fpath);
                
                /* Construct the html code */
                /* There's nothing wrong so I don't need to optimize it */
                $outhtml.='<tr><td><p class="text-center">'.htmlentities($ispd ? $val.'/' : $val).'</p></td><td style="text-align:right;width:150px;">'.($ispd ? htmlentities('<DIR>') : getfilesize($fpath)).'</td><td style="width:150px;"><a class="btn btn-dark" role="button" href="'.getviewlink($fopath,$passdown,$inpasswd).'" target="" style="width:64px;">Open</a></td></tr>'."\n";
            }
            $endhtml='<p class="lead text-center">Number of items: &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; '.strval($elecnt).'</p>'.$exs.$endhtml;
            $outhtml.='</tbody></table></div>';
        }
        else
            $endhtml=$exs.$endhtml;
        echo $endhtml.$pswdtxt.$outhtml;
        htmlmsg(false);
    }
    else
        diemsg('404 Not Found');
    
    ob_end_flush();
    if($db)
        $db->close();
?>