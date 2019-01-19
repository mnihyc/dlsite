# dlsite
文件下载站点 dl.mnihyc.tk 的主要源代码  
详细的设置可以在 inc.php 里面找到。
# 关于密码
.pass 文件为 ini-format  
其中目录的访问密码设置类似于 dirpass = ...  
文件的访问密码设置类似于 filepass = ...  
密码的查找顺序由：文件 < 当前目录 < 父目录  
密码中可以出现 urlencode() 的字符  
