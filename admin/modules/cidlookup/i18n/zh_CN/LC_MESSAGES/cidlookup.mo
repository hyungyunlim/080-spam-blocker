��          �      <      �  �  �     3     E     U     m  $   �       
   *     5     D     S     X  &   l  �   �       :   "  &   ]  �  �         �     �     �  e   �     &	     ?	     W	     c	     o	     ~	     �	     �	  �   �	     A
  0   E
     v
         	                                  
                                                          A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX CID Lookup Source CallerID Lookup CallerID Lookup Sources Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Enter a description for this source. Host name or IP address MySQL Host MySQL Password MySQL Username None Not yet implemented Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section Username to use in HTTP authentication Project-Id-Version: FreePBX 2.5 Chinese Translation
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2011-04-14 00:00+0800
Last-Translator: 周征晟 <zhougongjizhe@163.com>
Language-Team: EdwardBadBoy <zhougongjizhe@163.com>
Language: 
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Chinese
X-Poedit-Country: CHINA
X-Poedit-SourceCharset: utf-8
 查找源是你指定的用来解析入局的数字主叫ID的源，你可以把一条入局线路与特定的CID源链接起来。在这种工作方式下，你将获得更详细的CDR报告，报告中将包含直接从你的CRM里获取的内容。你也可以安装电话簿模块以提供简易的数字<->名字关联。请注意，名字查找将会减慢你的PBX服务器。 CID查找源 主叫ID查询 主叫ID查询 设置是否将查询结果缓存到astDB；它将覆盖当前设置。它不影响内部源的行为 为此源添加描述。 主机名或者IP地址 MySQL主机 MySQL密码 MySQL用户名 无 尚未实现 用于HTTP鉴权的密码 设置查询字符串，特殊标识符“[NUMBER]”会被替换成主叫号码<br/>例如：SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' 源 可以向呼叫者姓名查找源小节添加源 用于HTTP鉴权的用户名 