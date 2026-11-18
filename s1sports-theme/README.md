# S1 Sports — lokalt WordPress-tema

## Status

Version 0.9.1 är en lokal referensbyggnad. 18 svenska och engelska sidor har egna HTML-ögonblicksbilder i `snapshots/`. Temat laddar kopior av sidornas genererade CSS och hämtar bilder från WordPress Media på s1sports.se, så den visuella grunden kan renderas utan Breakdance-pluginets mallmotor. WordPress och WPML fortsätter välja sida och språk.

Vid flytt till en annan WordPress-installation behöver Media-adresserna i sidkopiorna och `data/media-urls.json` uppdateras.

Språkväxlaren använder WPML:s `wpml_active_languages` för att länka till sidans översättning på `s1sports.se` eller `s1sports.co`. Menyns flaggor, pil och storlek följer originalets WPML-markering och CSS. Swiper-biblioteket används för karuseller.

Startsidan, Om oss och college-sidorna har sliderinställningar per sidtyp och skärmstorlek. Pilarna är kopplade till rätt slider även när originalsidans interna ID skiljer sig från behållarens. Sociala länkar i sidfoten är dolda enligt önskemål tills riktiga profiladresser finns.

## Mötesformulär

Insticksprogrammet `s1sports-content` sparar varje giltig inskickning som en privat `s1_inquiry`-post under **Mötesförfrågningar** i WordPress-admin. Namn, sport, e-post, telefon, språk, meddelande och mejlstatus lagras. Formuläret skickar till `max@arvinge.se` via WordPress `wp_mail`; mottagaren kan ändras med konstanten `S1_CONTACT_RECIPIENT`. Den lokala PHP-miljön saknar fungerande SMTP och testinskickningen sparades men kunde inte mejlas. Verifiera leverans via Post SMTP i staging före driftsättning.

## Idrottare och ACF

Installera och aktivera även insticksprogrammet `s1sports-content` och ACF. Den befintliga inläggstypen `atleter` används på produktionssajten; insticksprogrammet registrerar den bara i en ny lokal installation där den saknas. Varje profil använder WordPress-titel och utvald bild samt ACF-fälten `s1_sport`, `s1_college`, `s1_featured` och `s1_display_order`. Idrottarsidan hämtar publicerade poster och filtrerar dem lokalt efter idrott. Startsidan visar upp till åtta profiler markerade för startsidan. De 25 redan kända profilerna har reservdata och reservbilder i temat tills motsvarande ACF-fält och utvalda bilder har fyllts i i WordPress.

`s1sports-content/wpml-config.xml` anger att `atleter` ska översättas, att idrott och college/status ska översättas och att startsidesmarkering och ordning ska kopieras mellan språken. Koppla befintliga svenska och engelska profiler som översättningar i WPML. WPML har inte kunnat testas i den lokala installationen eftersom licensierad WPML-kod inte finns där.

## Filtret på Våra idrottare

Ovanför idrottarna finns knappar för sport (Alla, Fotboll, Golf, Tennis, Friidrott) och en rullista för år och status. Rullistan läser fältet **Skola** på varje idrottare: "College Recruit 2027" blir **Åker till USA 2027**, och ett riktigt skolnamn räknas som **Signerade med skola**. Nya år dyker upp i listan av sig själva. Sporten **friidrott** läggs till i idrottarnas sportval av insticksprogrammet (`acf/load_field` på fältet `sport`).

## Development Golf och Tennis

Allt i den här delen redigeras i wp-admin.

- **Event:** menyn **Event** (från insticksprogrammet `s1sports-content`). Eventets namn skrivs i titelfältet och resten i ett formulär under det, i samma ordning som besökaren ser det: **1. Listan** (sport, typ, eventbild, datum, plats, kort beskrivning), **2. Rutan** (avgift, ålder, sista anmälan, om eventet, det här ingår), **3. Bilder** (rubrik och bildspel) och **4. Efter eventet** (resultat och höjdpunkter). Ett event flyttas automatiskt till **Tidigare** dagen efter slutdatumet. Eventen visas som en lista med datum, text och bild; hela raden öppnar eventets ruta.
- **Bilder:** fliken **3. Bilder** på varje event. Välj bilder ur mediabiblioteket, dra för att ändra ordning och skriv en egen rubrik om du vill (t.ex. "Om arenan"). Bilderna visas som ett bildspel med miniatyrer i eventets ruta: för tidigare event direkt under faktarutan, för kommande event efter "Det här ingår". Eventraden visar eventbilden (eller första bilden i bildspelet) och antalet bilder. Bildtexten hämtas från bildens bildtext i mediabiblioteket.
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
- Sociala länkar i sidfoten är avsiktligt dolda tills riktiga profiladresser finns.

Aktivera inte temat på produktionssajten förrän dessa punkter är klara.
