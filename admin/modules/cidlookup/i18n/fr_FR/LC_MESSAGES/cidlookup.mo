��          �      l      �  )   �  �       �     �     �     �     G  $   N     s  
   �     �     �     �     �     �  &   �  �   �     �  :   �  &   �  �  �  -   {  �  �     �	  "   �	     �	  �   �	  	   �
  )   �
     �
     �
     �
     �
               -  8   :  �   s       `     <   u                                    
                        	                                       Use OpenCNAM [https://www.opencnam.com/] A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX CID Lookup Source CallerID Lookup CallerID Lookup Sources Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Delete Enter a description for this source. Host name or IP address MySQL Host MySQL Password MySQL Username None Not yet implemented Password Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section Username to use in HTTP authentication Project-Id-Version: PACKAGE VERSION
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2018-07-18 19:12+0000
Last-Translator: philippebolduc <philippe@florenceinc.com>
Language-Team: French <http://*/projects/freepbx/cidlookup/fr/>
Language: fr_FR
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n > 1;
X-Generator: Weblate 2.19.1
  Utilise OpenCNAM [https://www.opencnam.com/] Une Source de Consultation vous permet de spécifier une source pour résoudre les IDs numériques des appelants parmi les appels entrants. Vous pouvez ensuite lier une route entrante a une source CID spécifique. De cette façon vous aurez des rapports CDR plus détaillés avec des informations prises directement de votre CRM. Vous pouvez également installer le module d'annuaire pour avoir une association numéro court <-> nom. Attention, la consultation de nom peu ralentir votre PBX Source de Consultation de CID Consultation de l'ID de l'appelant Sources Consult. ID Appelant Décide de mettre en cache ou non les résultats dans astDB; cela écrasera les valeurs actuelles. N'affectera pas le comportement de source Interne Supprimer Entrez une description pour cette source. Nom de l'hôte ou adresse IP Hôte MySQL Mot de Passe MySQL Nom d'utilisateur MySQL Aucun Pas encore implémenté Mot de passe Mot de Passe à utiliser lors de l'authentification HTTP Requête, le signe spécial '[NUMBER]' sera remplacé par le numéro de l'appelant<br/>exemple : SELECT nom FROM annuaire WHERE numéro LIKE '%[NUMBER]%' Source Les sources peuvent être ajoutées dans la section Sources de Consultation du Nom de l'Appelant Nom d'utlisateur à utiliser lors de l'authentification HTTP 