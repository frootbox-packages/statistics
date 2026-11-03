---
title: Statistik
---
Der Bildschirm **Statistik** zeigt, wie viele Menschen Deine Website
besuchen und was sie lesen: Besuche und Seitenaufrufe der letzten 7, 30,
90 oder 365 Tage im Vergleich zu den Tagen davor, die Besuche je Tag,
die meistbesuchten Seiten, woher Besucher kommen und ihre Geräte.

## Wie Besuche gezählt werden

Ein kleines Skript auf jeder Seite meldet den Aufruf. Es setzt keine
Cookies, speichert nichts im Browser und schickt nichts, das Besucher
unterscheidet: keine IP-Adresse, keinen Fingerabdruck. Deshalb braucht
die Website dafür keine Einwilligung.

- Ein **Seitenaufruf** ist jede Seite, die sich öffnet, auch nach dem
  Neuladen.
- Ein **Besuch** beginnt, wenn jemand von einer anderen Website kommt,
  aus einer Suchmaschine, über einen Link in einer E-Mail oder ein
  Lesezeichen. Weitere Seiten Deiner Website, Neuladen und Zurück
  beginnen keinen neuen Besuch.
- **Woher Besucher kommen** ist die Website des Links, dem sie gefolgt
  sind, z. B. eine Suchmaschine. „Direkt oder unbekannt“ heißt
  Lesezeichen, eine eingetippte Adresse oder ein Browser, der es nicht
  verrät. Ein Link mit `?utm_source=newsletter` erscheint als
  „newsletter“.

Nicht gezählt werden angemeldete Benutzer, der Bearbeitungsmodus,
Vorschauen, Suchmaschinen und andere Bots sowie Browser ohne JavaScript.
Die Zahlen liegen deshalb etwas unter den wahren, zeigen aber gut, wie
sich die Besuche entwickeln.

## Wann Zahlen erscheinen

Aufrufe werden erst gesammelt und bei jedem Lauf des Crons
zusammengezählt, außerdem jedes Mal, wenn Du diesen Bildschirm öffnest.
