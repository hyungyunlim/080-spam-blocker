��          �      \      �  �  �     S     e     u     �  $        2     J  
   S     ^     m     |     �  &   �  �   �     D  :   K  P   �  &   �  G  �  �  F     	     &	     9	  �   V	  2   �	     
     8
     ?
     K
     `
     y
     
  -   �
  �   �
     W  :   ^  r   �  1            
                                                       	                                   A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX CID Lookup Source CallerID Lookup CallerID Lookup Sources Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Enter a description for this source. Host name or IP address Internal MySQL Host MySQL Password MySQL Username None Not yet implemented Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section There are %s DIDs using this source that will no longer have lookups if deleted. Username to use in HTTP authentication Project-Id-Version: FreePBX cidlookup
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2011-04-14 00:00+0100
Last-Translator: Mikael Carlsson <mickecamino@gmail.com>
Language-Team: 
Language: 
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Swedish
X-Poedit-Country: SWEDEN
 En källa för nummerpresentation ger dig en möjlighet att få uppslag på namn för inkommande samtal. Du kan sedan länka en inkommande väg till denna källa. På detta vis kan du få en mer detaljerad samtalsrapport med information t.ex. från ert eget CRM. Du kan också installera telefonboksmodulen där ett mindre antal telefonnummer med namn kan skrivas in. Tänk på att namnuppslag kan orsaka fördröjningar innan samtalet kopplas upp. Källa för nummerpresentation Nummerpresentation Källa f. nummerpresentation Välj om du ska mellanlagra resultaten i astDB; det kommer att skriva över eventuella poster som redan finns. Används inte om källa är Internal. Skriv en kortfattad beskrivning för denna källa. Datornamn eller IP-adress Intern MySQL-dator Lösenord för MySQL Användarnamn för MySQL Ingen Inte implementerad Lösenord att använda vid HTTP-autentisering Fråga, variabeln '[NUMBER]' kommer att bytas ut mot inkommande nummerpresentation<br>t.ex.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Källa Källor kan läggas till i Källor för nummerpresentation Det finns %s Inkommande vägar för denna källa, om denna källa tas bort kommmer ingen nummeruppslagning att ske Användarnamn att använda vid HTTP-autentisering 