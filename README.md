# dlsite
文件下载站点 [https://dl.mnihyc.com](https://dl.mnihyc.com) 的主要源代码  

详细的设置可以在 [inc.php](https://github.com/mnihyc/dlsite/blob/master/inc.php) 里面找到。
# 关于密码
按照优先级升序  
* 其中文件/目录的访问密码设置类似于 ```filepass/dirpass = ...```  
* 或者可以限定访问密码的有效范围仅为当前目录 ```curfilepass/curdirpass = ...```  
* 或者可以取消这个文件/目录的访问密码 ```nofilepass/nodirpass = yes```  
* 或者可以取消当前文件/目录的访问密码 ```nocurfilepass/nocurdirpass = yes```  
* 或者可以设置子目录中的文件/目录的访问密码 ```subfilepass/dirpass = ...```  
* 或者可以取消子目录中的文件/目录的访问密码 ```nofilepass/nodirpass = yes```  

密码的查找顺序为：```文件 > 当前目录 > 父目录``` 
密码的匹配顺序（当前）为：```nocur()pass > cur()pass > no()pass > ()pass```  
密码的匹配顺序（父节点）为：```subno()pass > sub()pass > no()pass > ()pass```  
密码的匹配顺序（总）为：  
``` 当前文件/目录 -> nocur()pass -> cur()pass -> no()pass -> ()pass -> 父目录 ->```  
```|-> subno()pass -> sub()pass -> nocur()pass -> cur()pass -> no()pass -> ()pass <-|```  
# 其他功能/特性
* 显示额外信息 ```fileextrainfo/dirextrainfo = ...```  
* 在 ```URI``` 后增加 ```?manage``` 进入管理界面  
* 数据存储在 ```CONFIG_FILE```（```SQLite3``` 数据库）的 ```CONFIG``` 表中  
* 计算管理密码的方式：```md5(md5(PSWD).'+'.sha1(PSWD))```，其中 ```PSWD``` 为你的明文密码  
* 数据库中各存储的项名分别为 ```NAME```、```TYPE```及```VALUE```，其中主关键字为 ```(NAME,TYPE)```  
* 使用 ```MANAGE_PASSWORD``` 可以不受限制的浏览目录/文件  