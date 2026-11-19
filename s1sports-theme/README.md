# S1 Sports — lokalt WordPress-tema

## Status

Version 0.9.1 är en lokal referensbyggnad. 18 svenska och engelska sidor har egna HTML-ögonblicksbilder i `snapshots/`. Temat laddar kopior av sidornas genererade CSS och hämtar bilder från WordPress Media på s1sports.se, så den visuella grunden kan renderas utan Breakdance-pluginets mallmotor. WordPress och WPML fortsätter välja sida och språk.

Vid flytt till en annan WordPress-installation behöver Media-adresserna i sidkopiorna och `data/media-urls.json` uppdateras.

Språkväxlaren använder WPML:s `wpml_active_languages` för att länka till sidans översättning på `s1sports.se` eller `s1sports.co`. Menyns flaggor, pil och storlek följer originalets WPML-markering och CSS. Swiper-biblioteket används för karuseller.

Startsidan, Om oss och college-sidorna har sliderinställningar per sidtyp och skärmstorlek. Pilarna är kopplade till rätt slider även när originalsidans interna ID skiljer sig från behållarens. Sociala länkar i sidfoten visas bara när en adress är ifylld under **Meny och sidfot** i wp-admin.

## Mötesformulär

Insticksprogrammet `s1sports-content` sparar varje giltig inskickning som en privat `s1_inquiry`-post under **Mötesförfrågningar** i WordPress-admin. Namn, sport, e-post, telefon, språk, meddelande och mejlstatus lagras. Formuläret skickar till `max@arvinge.se` via WordPress `wp_mail`; mottagaren kan ändras med konstanten `S1_CONTACT_RECIPIENT`. Den lokala PHP-miljön saknar fungerande SMTP och testinskickningen sparades men kunde inte mejlas. Verifiera leverans via Post SMTP i staging före driftsättning.

## Idrottare och ACF

Installera och aktivera även insticksprogrammet `s1sports-content` och ACF. Den befintliga inläggstypen `atleter` används på produktionssajten; insticksprogrammet registrerar den bara i en ny lokal installation där den saknas. Varje profil använder WordPress-titel och utvald bild samt ACF-fälten `s1_sport`, `s1_college`, `s1_featured` och `s1_display_order`. Idrottarsidan hämtar publicerade poster och filtrerar dem lokalt efter idrott. Startsidan visar upp till åtta profiler markerade för startsidan. De 25 redan kända profilerna har reservdata och reservbilder i temat tills motsvarande ACF-fält och utvalda bilder har fyllts i i WordPress.

`s1sports-content/wpml-config.xml` anger att `atleter` ska översättas, att idrott och college/status ska översättas och att startsidesmarkering och ordning ska kopieras mellan språken. Koppla befintliga svenska och engelska profiler som översättningar i WPML. WPML har inte kunnat testas i den lokala installationen eftersom licensierad WPML-kod inte finns där.

## Texter och bilder på de övriga sidorna

Sidorna som bygger på ögonblicksbilder (Start, Juniorutveckling, Om oss, Våra tjänster, Collegerekrytering, Professionell rådgivning, Kontakt, Boka möte, Våra idrottare, på båda språken) har rutan **Innehåll på sidan** i wp-admin. Den har en flik per del av sidan, i sidans ordning, och i varje flik sidans texter och bilder: rubriker (fet och kursiv del), texter, knapptexter, små etiketter, bilder och bakgrundsbilder. Fälten hittas automatiskt i ögonblicksbilden och sidans CSS. Utseendet ändras aldrig, bara orden och bilderna.

- **Texter:** fältet visar texten som står på sidan i dag. Bara ändrade texter sparas (`s1_txt_<elementnummer>`, rubrikens delar `_b`/`_i`); en text som är som i dag sparas tom, och ett tömt fält visar dagens text igen. Ny rad blir radbrytning, tom rad nytt stycke. Texterna skrivs separat på svenska och engelska.
- **Bilder:** fältet visar bilden som syns i dag; tomt fält behåller den. En vald bild sparas som `s1_hero_image` (toppbilden) eller `s1_img_<elementnummer>`. Breakdance ger samma element samma nummer på båda språken, så WPML kopierar bildvalet till den engelska sidan. Den engelska startsidan har två kort (Swimming, Amerikansk fotboll) som inte finns på svenska; de bilderna väljs på den engelska sidan. Temats egna bakgrundsbilder är redan mörka; en vald bakgrundsbild får därför en mörk gradient ovanpå (starkast under menyn på toppbilden) så att vit text och menyn syns även på ljusa foton.
- **Ingår inte här:** sidhuvud, meny och sidfot (se nästa avsnitt), block som delas mellan flera sidor (omdömen, idrottarslidern, faktablocket), idrottarkorten och sektionen på Juniorutveckling som ersätts av Golf/Tennis-korten. Formulärens fält ingår inte heller.

Fälten registreras bara för sidan som redigeras. Sidorna öppnas i den klassiska redigeraren. Kod: `inc/page-content.php`.

## Meny och sidfot

Menyn högst upp och sidfoten längst ner redigeras under **Meny och sidfot** i wp-admin (eget menyval, kräver rätten att redigera sidor), med en flik för svenska och en för engelska. De gäller alla sidor på språket, även Development-sidorna.

- **Menyn:** rader med text, länk och nivå. En rad med nivån *Undermeny* hamnar i rullgardinen under huvudraden ovanför (som Collegerekrytering under Våra tjänster). Rader kan läggas till, flyttas och tas bort. Knappen i menyn (Ansök nu) har egen text och länk.
- **Sidfoten:** rutan överst (rubrik i två delar, knapptext, knappens länk), de två länkkolumnerna (rubrik och länkar), sociala medier (en adress per tjänst; tomt fält visas inte) och raden längst ner.

Standardinnehållet läses från startsidans ögonblicksbild för språket. Det som sparas lagras i alternativen `s1_site_parts_sv` och `s1_site_parts_en`, och **Återställ till ursprunget** tar bort det. Bara texter och länkar skrivs in i den befintliga markeringen, så utseendet ändras inte. Kod: `inc/site-parts.php` (anropas från `s1_prepare_snapshot_html`).

## Filtret på Våra idrottare

Ovanför idrottarna finns knappar för sport (Alla, Fotboll, Golf, Tennis, Friidrott) och en rullista för år och status. Rullistan läser fältet **Skola** på varje idrottare: "College Recruit 2027" blir **Åker till USA 2027**, och ett riktigt skolnamn räknas som **Signerade med skola**. Nya år dyker upp i listan av sig själva. Sporten **friidrott** läggs till i idrottarnas sportval av insticksprogrammet (`acf/load_field` på fältet `sport`).

## Development Golf och Tennis

Allt i den här delen redigeras i wp-admin.

- **Event:** menyn **Event** (från insticksprogrammet `s1sports-content`). Eventets namn skrivs i titelfältet och resten i ett formulär under det, i samma ordning som besökaren ser det: **1. Listan** (sport, typ, eventbild, datum, plats, kort beskrivning), **2. Rutan** (avgift, ålder, sista anmälan, om eventet, det här ingår), **3. Efter eventet** (resultat och höjdpunkter) och **4. Bilder** (rubrik och bildspel). Ett event flyttas automatiskt till **Tidigare** dagen efter slutdatumet. Eventen visas som en lista med datum, text och bild; hela raden öppnar eventets ruta.
- **Bilder:** fliken **4. Bilder** på varje event. Välj bilder ur mediabiblioteket, dra för att ändra ordning och skriv en egen rubrik om du vill (t.ex. "Om arenan"). Bilderna visas som ett bildspel med miniatyrer i eventets ruta: efter texterna: för tidigare event efter "Resultat och höjdpunkter", för kommande event efter "Det här ingår". Eventraden visar eventbilden (eller första bilden i bildspelet) och antalet bilder. Bildtexten hämtas från bildens bildtext i mediabiblioteket.
- **Development-sidor:** vanliga sidor med mallen **Development (sport)**. Sidan redigeras i ett formulär direkt under titeln, i samma ordning som sidan: **1. Toppen** (toppbild, rubrik, ingress), **2. Event** (sport och rubrik ovanför eventen), **3. Avslutning** (bild, rubrik, text, knapp) och **4. Kort på Juniorutveckling** (egen kortbild, rubriker och text). Tomma fält visar den grå texten. Sidor med mallen öppnas i den klassiska redigeraren; en ny sida får formuläret efter att mallen valts och sidan sparats.
- **Juniorutveckling:** sektionen under toppbilden visar ett kort per publicerad Development-sida. Rubrik och text redigeras i fälten på sidan Juniorutveckling.
- **Anmäl intresse** leder till Boka möte med eventets namn och sport ifyllda i formuläret.

Engelska versioner skapas som WPML-översättningar av event och sidor. `wpml-config.xml` i temat och insticksprogrammet anger vilka fält som översätts och vilka som kopieras (sport, datum, bilder).

## Översättning (engelska på s1sports.co)

Engelska sidor visar bara engelska event och engelska Development-sidor. Allt nytt behöver därför översättas i WPML.

1. **WPML → Inställningar → Översättning av inläggstyper:** kontrollera att **Event** är översättningsbar. `wpml-config.xml` i insticksprogrammet ställer in det.
2. **WPML → Inställningar → Synkronisering av inlägg och sidor:** se till att sidmallen kopieras, så att engelska Development-sidor får mallen **Development (sport)**.
3. **Översätt varje Development-sida och varje event.** Sport, datum och bilder kopieras automatiskt från den svenska versionen; texterna översätts. Fältgrupperna står i ACF Multilinguals läge **Expert**, och koden anger för varje fält om det översätts eller kopieras (samma som `wpml-config.xml`).
4. **Utkast fungerar per språk.** En engelsk sida eller ett engelskt event syns först när den engelska versionen är publicerad.
5. **Kör kontrollen efter uppladdning:** `node scripts/check-live.mjs` läser båda domänerna och visar om något är fel (språk, meny, språkväxlare, Development-sidor och kort).

## Kvar innan skarp installation

- Jämför samtliga 18 sidversioner vid dator- och mobilbredd och rätta varje skillnad.
- Testa navigation, karuseller, språkväxling och formulär i en WordPress stagingmiljö med WPML aktivt och Breakdance avstängt.
- Flytta övriga sidors text och bilder från statiska ögonblicksbilder till redigerbara WordPress-fält eller block utan att ändra utseendet. I nuläget är bara idrottarkorten dynamiska.
- Sociala länkar i sidfoten visas när adresser fylls i under Meny och sidfot.

Aktivera inte temat på produktionssajten förrän dessa punkter är klara.
