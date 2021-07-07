# dlsite
文件下载站点 [https://dl.mnihyc.com](https://dl.mnihyc.com) 的主要源代码  

详细的设置可以在 [inc.php](https://github.com/mnihyc/dlsite/blob/master/inc.php) 里面找到。
# 关于密码
按照优先级升序  
* 其中文件/目录的访问密码设置类似于 ```file/dirpass = ...```  
* 或者可以限定密码的有效范围仅为当前目录 ```curfile/dirpass = ...```  
* 或者可以取消这个文件/目录的访问密码（包括来自子目录的可能） ```nofile/dirpass = yes```  
* 或者可以取消当前文件/目录的访问密码（不对子目录生效） ```nocurfile/dirpass = yes```  
* 或者可以设置子目录中的文件/目录的访问密码 ```subfile/dirpass = ...```  
* 或者可以取消子目录中的文件/目录的访问密码 ```subnofile/dirpass = yes```  

密码的查找顺序为：```文件 > 当前目录 > 父目录``` 
密码的匹配顺序（当前）为：```nocur()pass > cur()pass > no()pass > ()pass```  
密码的匹配顺序（父节点）为：```subno()pass > sub()pass > no()pass > ()pass```  
密码的匹配顺序（总）为：  
``` 当前文件/目录 -> nocur()pass -> cur()pass -> no()pass -> ()pass -> 父目录 ->```  
```|-> subno()pass -> sub()pass -> nocur()pass -> cur()pass -> no()pass -> ()pass <-|```  
# 关于 API
* 详细的设置可以在 [api.php](https://github.com/mnihyc/dlsite/blob/master/api.php) 里面找到。  
* 目前仅支持 OneDrive 提取预览/下载链接  
# 其他功能/特性
* 显示额外信息 ```file/dirextrainfo = ...```  
* ~~在 ```URI``` 后增加 ```?manage``` 进入管理界面~~ （过时的）  
* 访问 ```/manage?p=[PATH]``` 进入管理界面
* 数据存储在 ```CONFIG_FILE```（```SQLite3``` 数据库）的 ```CONFIG``` 表中  
* 计算管理密码的方式：```md5(md5(PSWD).'+'.sha1(PSWD))```，其中 ```PSWD``` 为你的明文密码  
* 数据库中各存储的项名分别为 ```NAME```、```TYPE```及```VALUE```，其中主关键字为 ```(NAME,TYPE)```  
* 使用 ```MANAGE_PASSWORD``` 可以不受限制的浏览目录/文件  
* 所有带有密码文件的下载直链将在生成的 24h 后过期，不带密码的文件不受此限制