��    H      \  a   �            !     (     0  
   ?  *   J     u     �     �  !   �     �     �     �     �     �  	            "     B     Q     a     g     {     �  �   �     )	     <	     D	     c	     z	  �   �	     
      
     &
  j   +
     �
  &   �
     �
  0   �
     !     1     7  
   <     G     W     m     |     �     �     �  7   �     �  	             &  +   -  @   Y  �   �     �  �   �  &   3     Z     l          �     �     �     �     �  
   �     �     �  �  �  #   �                3  "   @     c     p     �     �     �     �     �     �     �     �     �           	        %     ,     E     R  x   _  !   �     �               6  y   F     �     �     �  v   �     O     b     ~  -   �     �     �     �     �     �               .     E     R      _  E   �     �  	   �     �     �  !      <   "    _     n  u   �     �       '   $     L     S     f     |     �     �     �     �     �     A   	       $          =      %          7                      2       C   !   '   8   
   B   ;   <   *   H              4   5         ?      D   :   1      ,                                        F                  3   .      #   @          9   &   -   0                  E      >         "   )   +                                      /   G   (   6    *-prim Actions Add Ring Group Alert Info Always transmit the Fixed CID Value below. Announcement CID Name Prefix Call Recording Change External CID Configuration Confirm Calls Default Delete Description Destination if no answer Dont Care Enable Call Pickup Enable this if you're calling external numbers that need confirmation - eg, a mobile phone may go to voicemail which will pick up the call. Enabling this requires the remote side push 1 on their phone before the call is put through. This feature only works with the ringall ring strategy Extension List Fixed CID Value Force Force Dialed Number Group Description INUSE If you select a Music on Hold class to play, instead of 'Ring', they will hear that instead of Ringing while they are waiting for someone to pick up. Ignore CF Settings Inherit Invalid Group Number specified Invalid time specified List Ring Groups Message to be played to the caller before dialing this group.<br><br>To add additional recordings please use the "System Recordings" MENU above Mode Never None Only ringall, ringallv2, hunt and the respective -prim versions are supported when confirmation is checked Play Music On Hold Please enter a valid Group Description Please enter an extension list. Provide a descriptive title for this Ring Group. Remote Announce Reset Ring Ring Group Ring Group %s:  Ring Group Membership Ring Group: %s Ring Group: %s (%s) Ring Groups Ring Strategy Ring Time (max 300 sec) Ring all available channels until one answers (default) Ring-Group Number RingGroup Skip Busy Agent Submit Take turns ringing each available extension The number users will dial to ring extensions in this ring group These modes act as described above. However, if the primary extension (first in list) is occupied, the other extensions will not be rung. If the primary is FreePBX DND, it won't be rung. If the primary is FreePBX CF unconditional, then all will be rung This ringgroup Time in seconds that the phones will ring. For all hunt style ring strategies, this is the time for each iteration of phone(s) that are rung Time must be between 1 and 300 seconds Too-Late Announce Warning! Extension default firstavailable firstnotonphone hunt is already in use is not allowed for your account memoryhunt none ringall Project-Id-Version: FreePBX 2.5 Chinese Translation
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2015-10-16 08:39+0200
Last-Translator: james <zhulizhong@gmail.com>
Language-Team: Simplified Chinese <http://weblate.freepbx.org/projects/freepbx/ringgroups/zh_CN/>
Language: zh_CN
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=1; plural=0;
X-Generator: Weblate 2.2-dev
X-Poedit-Language: Chinese
X-Poedit-Country: CHINA
X-Poedit-SourceCharset: utf-8
 依从主分机（策略名-prim） 命令 添加拨号小组 警告信息 总是传输以下Fixed CID 值。 语音播报 主叫ID名的前缀 呼叫录音: 修改外部CID配置 呼叫确认 默认 删除 描述 无人接听时的目的地 不管 启用呼叫接听 如果你要呼叫需要确认的外部号码时，就启用此项——比如，一个移动电话会被转移，而由语音邮箱接听。要启用这个选项，需要远端在接通前在电话上按下1。这个功能只会在全部响铃的策略下起作用。 分机列表 固定CID 强制 强制使用已拨号码 小组描述 正在使用 如果你选择了一个等待音乐类别，而不是“振铃”，呼叫者在等待接听的时候会听到音乐。 忽略呼叫转移的相关设置 继承 指定了无效的组号码 指定了无效的时间 振铃组列表 呼叫这个组之前，对呼叫方播放语音提示。<br><br>请到"System Recordings" 菜单添加语音提示文件 模式 从不录音 无 若“确认”选项被启用，就只支持全部响铃、全部响铃2、搜寻和各自的主分机依从策略。 播放音乐等待 请输入有效的组描述 请输入一个分机列表。 为拨号小组提供一个描述性的标题 远端播报 重新设置 振铃 拨号小组 拨号小组 %s： 振铃组成员 拨号小组：%s 拨号小组：%s (%s) 拨号小组 振铃策略 最长振铃时间(最长300秒) 全部可用频道都响铃直到其中一个接听（默认设置） 拨号小组号码 振铃组 跳过忙碌的坐席 提交 在可用的分机上轮流响铃 用户拨打此号码以呼叫这个拨号小组中的分机 这些模式按上述的方式工作。然而，如果主分机（列表中的第一个）占线，其他的分机就不会响铃。如果主分机是设置了免打扰，它就不会振铃。如果主分机设置了无条件转移呼叫，那么所有的分机会响铃 这个拨号小组 电话响铃的秒数。对于所有的搜寻式的响铃策略，这是每次搜寻出的电话的响铃的时间。 时间必须在1到300秒之内 超时播报 警告！你的帐户无法使用分机 默认 首个可用频道 首个未离钩频道 搜寻 已经在使用中了   记忆性搜寻 无 全部响铃 