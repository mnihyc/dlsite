# dlsite
文件下载站点 dl.mnihyc.com 的主要源代码  

详细的设置可以在 inc.php 里面找到。
# 关于密码
.pass 文件为 **ini**-format  
其中文件/目录的访问密码设置类似于 filepass/dirpass = ...  
或者可以取消这个文件/目录的访问密码 nofilepass/nodirpass = yes  
显示额外信息 fileextrainfo/dirextrainfo = ...

密码的查找顺序由：文件 < 当前目录 < 父目录  
密码中由 urldecode() 处理，特殊字符如 "%22 '%27 (%28 )%29 等**一定**要转码  