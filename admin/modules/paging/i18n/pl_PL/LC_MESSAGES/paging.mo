��    8      �  O   �      �  �   �  >   f  �   �  �  +     �     �  ;   	     B	     W	     \	     d	     |	     �	     �	     �	     �	     �	     �	     �	     �	     �	     �	     �	      
     
     
     
     1
     L
     f
     v
     �
     �
     �
     �
     �
     �
  c   �
  
   :     E     R     f     r     �     �     �     �     �     �     �     �     �     �        -     �  5  �   ,  D   �  �   9  D  �  
   1     <  P   O  -   �     �  	   �     �     �                    .     :     F  
   N  
   Y     d     w  
   }  	   �     �     �     �     �     �     �          "  #   7     [     m     q     x  j   �     �     �     	          *     E     `     t  $   �  	   �     �     �     �  
   �     �  
   �  0   �     *   #           "       -          8   
       7             +   )              0         (                         %      /                 $                      1                     ,   4   .      3   !                &      6   5                  '                 2         	    <strong>Force</strong><br> Send the headers telling the phone to go into auto answer mode. This may not work, and is dependant on the phone. <strong>Reject</strong><br> Return a BUSY signal to the caller <strong>Ring</strong><br> Treat the page as a normal call, and ring the extension (if Call Waiting is disabled, this will return BUSY <ul>
<li><b>"Skip"</b> will not page any busy extension. All other extensions will be paged as normal</li>
<li><b>"Force"</b> will not check if the device is in use before paging it. This means conversations can be interrupted by a page (depending on how the device handles it). This is useful for "emergency" paging groups.</li>
<li><b>"Whisper"</b> will attempt to use the ChanSpy capability on SIP channels, resulting in the page being sent to the device's earpiece "whispered" to the user but not heard by the remote party. If ChanSpy is not supported on the device or otherwise fails, no page will get through. It probably does not make too much sense to choose duplex if using Whisper mode.</li>
</ul> Actions Add Page Group Annoucement to be played to remote party. Default is a beep Auto-answer defaults Beep Default Default Group Inclusion Default Page Group Delete Description Device List Disable Disabled Duplex Enabled Exclude Extension Options Force Group Description Include Intercom Intercom Mode Intercom Override Intercom from %s: Disabled Intercom from %s: Enabled Intercom prefix Intercom: Disabled Intercom: Enabled Internal Auto Answer List Page Groups No None Not Selected Override the speaker volume for this page. Note: This is only valid for Sangoma phones at this time Page Group Page Group:  Page Group: %s (%s) Page Groups Paging Extension Paging Group %s : %s Paging Groups Paging and Intercom Paging and Intercom settings Reject Reset Ring Selected Settings Skip Submit The number users will dial to page this group Project-Id-Version: PACKAGE VERSION
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2023-09-13 05:56+0000
PO-Revision-Date: 2017-05-31 23:43+0200
Last-Translator: Michal <mboltz@tlen.pl>
Language-Team: Polish (Polish) <http://weblate.freepbx.org/projects/freepbx/paging/pl_PL/>
Language: pl_PL
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=3; plural=n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;
X-Generator: Weblate 2.4
 <strong>Zmuś</strong><br> Wyślij nagłówki nakazujące telefonowi by przeszedł w tryb automatycznej odpowiedzi. Funkcja ta jest zależna od aparatu i w niektórych telefonach może nie działać. <strong>Odrzuć</strong><br> Zwróć sygnał ZAJĘTY do dzwoniącego <Strong> Brzmienie </ strong> <br> Traktuj stronę jako zwykłe połączenie i brzmienie rozszerzenia (jeśli funkcja oczekiwania połączeń jest wyłączona, to odeśle ZAJĘTY <ul>
<li><b>"Pomiń"</b> nie będzie stroną żadnego zajętego rozszerzenia. Wszystkie pozostałe rozszerzenia będą wyświetlane w trybie normalnym</li>
<li><b>"Siła"</b> nie sprawdzi, czy urządzenie jest używane przed jej stronicowaniem. Oznacza to, że rozmowy mogą zostać przerwane przez stronę (w zależności od sposobu obsługi urządzenia). Jest to użyteczne dla grup stronicowania "awaryjnego".</li>
<li><b>"Szeptać"</b> Będzie próbował użyć funkcji ChanSpy w kanałach SIP, co powoduje wysłanie do słuchawki urządzenia "szeptem" do użytkownika ale  ale nie słyszanej przez zdalną stronę. Jeśli ChanSpy nie jest obsługiwane w urządzeniu lub w inny sposób nie powiedzie się, żadna strona nie zostanie przejęta.Prawdopodobnie nie ma sensu wybierać dupleksu, jeśli używasz trybu Whisper.</li>
</ul> Działania Dodaj grupę stron Ogłoszenie do odegrania na zdalnej stronie. Domyślnie jest sygnał dźwiękowy Automatyczne odbieranie ustawień domyślnych Dźwięk Domyślne Domyślna integracja grupy Domyślna grupa stron Kasuj Opis Lista urządzeń Wyłączyć Wyłączony Dupleks Włączone Wykluczać Opcje rozszerzenia Siła Opis grupy Zawierać Interkom Tryb interkomowy Zastąpienie interkomu Interkom z %s: wyłączony Interkom z %s: włączony Prefiks interkomowy Interkom: wyłączony Interkom: włączony Wewnętrzna odpowiedź automatyczna Lista grup strony Nie Żadne Nie wybrano Zastąp głośność głośników dla tej strony. Uwaga: dotyczy to obecnie tylko telefonów marki Sangoma Grupa stron Grupa stron:  Grupa stron: %s (%s) Grupy stron Rozszerzenie stronicowania Grupa stronicowania %s: %s Grupy stronicowania Stronicowanie i interkom Ustawienia stronicowania i interkomu Odrzucać Ponowne ustawienia Dzwonek Wybrany Ustawienia Pomiń Zatwierdź Liczba użytkowników wybierze stronę tej grupy 