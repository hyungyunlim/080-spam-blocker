��          �            h  �  i     �     �  $   }     �  
   �     �     �     �  &   �  �        �  :   �  &   �  q     �  r     d  �   ~  %   	     =	  
   S	     ^	     l	     �	     �	     �	     )
  :   1
  (   l
                                         	          
                             A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX CID Lookup Source Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Enter a description for this source. Host name or IP address MySQL Host MySQL Password MySQL Username None Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section Username to use in HTTP authentication Project-Id-Version: 2.4
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2011-04-14 00:00+0100
Last-Translator: Lónyai Gergely <alephlg@gmail.com>
Language-Team: Magyar <support@freepbx.hu>
Language: 
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Hungarian
X-Poedit-Country: HUNGARY
X-Poedit-SourceCharset: utf-8
 A CID meghatározó forrás használatakor a hívó szám alapján a forrásban meghatározott név kiválasztásra kerül. Ehhez mindössze a bejövő hívásnál ki kell jelölnöd egy CID forrást. Ezen az úton átmenő hívások részletesebb, értelmezhetőbb CDR jelentést produkálnak, aminek nagy hasznát tudod venni például egy CDM-ben. A Telefonkönyv modullal egy kis szám <-> név kapcsolatot tudsz kialakítani. Hátránya, hogy a névfeloldás lassabbá teheti a PBX rendszert. CID meghatározó forrás Legyen, vagy ne legyen a lekérdezés cachelve a belső astDB-ben; ez különbözhet az mindenkori értéktől. Nincs a belső adatbázisra értelmezve. Adj meg egy nevet ehhez a forráshoz. Gépnév vagy IP-cím MySQL gép MySQL jelszó MySQL felhasználónév Nincs Jelszó a HTTP azonosításhoz Lekérő string, ahol a '[NUMBER] jelöli a hívó számát.<br/>Pl.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Forrás Hozzáad egy forrást a CID meghatározó forrás részhez Felhasználónév a HTTP azonosításhoz 