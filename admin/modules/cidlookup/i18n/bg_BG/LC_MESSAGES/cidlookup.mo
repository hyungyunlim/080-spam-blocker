��          �      l      �  �  �     c     i     {     �     �  $   #     H     `  
   i     t     �     �     �  &   �  �   �     Z  :   a  P   �  &   �  �    !  �  
     (   *     S     k  �   �  A   o  )   �     �     �     �  '        6  *   ?  -   j  �   �       h   �  �   �  B   �                                    	                           
                                   A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX Admin CID Lookup Source CallerID Lookup CallerID Lookup Sources Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Enter a description for this source. Host name or IP address Internal MySQL Host MySQL Password MySQL Username None Not yet implemented Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section There are %s DIDs using this source that will no longer have lookups if deleted. Username to use in HTTP authentication Project-Id-Version: FreePBX v2.5
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2014-07-21 15:37+0200
Last-Translator: Chavdar <chavdar_75@yahoo.com>
Language-Team: Bulgarian <http://git.freepbx.org/projects/freepbx/cidlookup/bg/>
Language: bg_BG
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n != 1;
X-Generator: Weblate 1.10-dev
X-Poedit-Language: Bulgarian
X-Poedit-Country: BULGARIA
X-Poedit-SourceCharset: utf-8
 Източникът на Следене ви позволява да определите източник за анализиране на цифрови CallerID-та на входящите обаждания, след което можете да свържете Входящ Маршрут с определен CID източник. По този начин ще имате по-детайлни CDR отчети с информация взета директно от CRM. Също така можете да инсталирате модула Телефонен Указател за да имате някаква номер <-> име асоциация. Имайте предвид, че следенето може да натовари вашата телефонна централа Админ Източник на CID Следене CallerID Следене CallerID Следене Преценете дали да кеширате или не резултатите в astDB; това ще отмени настоящите настройки. Не се отразява на  Вътрешните източници Въведете описание за този източник. Ине на хост или IP адрес Вътрешен MySQL Хост MySQL Парола MySQL Потребителско Име Няма Все още не е реализиран Парола за HTTP оторизиране Запитване, определеното означение '[NUMBER]' ще бъде заменено с номера на обаждащия се<br/>например: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Източник Източниците могат да бъдат добавяни в меню 'CallerID Следене' Има %s DID използващи този източник, които няма да могат да се следят ако го изтриете. Потребителско Име за HTTP оторизиране 