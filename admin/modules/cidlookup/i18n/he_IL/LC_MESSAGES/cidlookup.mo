��          �            h  �  i     �     �  $   }     �  
   �     �     �     �  &   �  �        �  :   �  &   �  �      m  �  '   S  �   {  +   L	  )   x	      �	     �	     �	     �	  5   
  �   =
     �
  U     :   X                                         	          
                             A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX CID Lookup Source Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Enter a description for this source. Host name or IP address MySQL Host MySQL Password MySQL Username None Password to use in HTTP authentication Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Source Sources can be added in Caller Name Lookup Sources section Username to use in HTTP authentication Project-Id-Version: FreePBX 2.2.0
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2011-04-14 00:00+0300
Last-Translator: Shimi <shimi@shimi.net>
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
 מקור חיפוש מאפשר לך לציין מקור לתרגום מספרים ממערכת זיהוי השיחה של שיחות נכנסות, כך שתוכל לקשר נתיב שיחה נכנסת למקור שיחה מסויים. פעולה זו יכולה גם ליצור דוחות שיחה מפורטים יותר באמצעות נתונים הנלקחים ישירות מה CRM שלך. אתה יכול גם להתקין את מודול ספר הטלפונים כדי שיהיה לך שיוך קטן של מספר טלפון >-< שם. שים לב, חיפוש שמות עשוי להאט את המרכזיה שלך! מקור חיפוש זיהוי שיחה החלט אם לשמור או לא לשמור את התוצאות ב astDB; פעולה זו תדרוס ערכים קיימים. היא אינה משפיעה על התנהגות המקורות הפנימיים הכנס תיאור עבור מקור זה. שם מחשב מארח או כתובת IP כתובת המארח של MySQL סיסמת משתמש ב MySQL שם משתמש ב MySQL אף אחד סיסמא שבה יש להשתמש באימות HTTP שאילתא, כאשר הטוקן המיוחד '[NUMBER]' יוחלף במספר הטלפון של המתקשר<br />לדוגמא: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' מקור מקורות ניתן להוסיף בחלק מקורות חיפוש שם המתקשר שם משתמש שבו יש להשתמש באימות HTTP 