# MIA POS Payment Gateway pentru OpenCart

Acceptați plăți în magazinul dvs. OpenCart prin sistemul de plăți MIA POS.

## Descriere

Acest plugin integrează MIA POS ca metodă de plată în magazinul dvs. OpenCart. MIA POS este un sistem de plată oferit de Finergy Tech care vă permite să acceptați plăți prin coduri QR și solicitări de plată directe.

### Funcționalități

- Acceptarea plăților prin coduri QR
- Suport pentru plăți Request to Pay (RTP)
- Actualizare automată a statutului comenzilor
- Procesare securizată a plăților cu verificare de semnătură
- Suport multilingv (RO, RU, EN)
- Integrare ușoară cu OpenCart

## Cerințe

- Înregistrare pe platforma MIA POS
- OpenCart versiunea 3.x
- PHP 7.2 sau o versiune mai nouă
- Certificat SSL instalat
- Extensiile PHP: _curl_ și _json_ trebuie să fie activate

## Instalare

1. Descărcați fișierul extensiei: **mia-pay-gateway.ocmod.zip**.
2. În panoul de administrare OpenCart, accesați **Extensii > Instalator**.
3. Faceți clic pe **Încărcați** și selectați fișierul extensiei. După încărcare, OpenCart va începe procesul de instalare.
4. Mergeți la **Extensii > Modificări** și faceți clic pe butonul **Actualizare**.
5. Navigați la **Extensii > Extensii** și selectați tipul **Plăți**. Veți vedea **MIA POS Payment Gateway** în listă.
6. Faceți clic pe **Instalare**.
7. Faceți clic pe **Editați** pentru a configura setările necesare.

## Configurare

### Parametri necesari

- **Merchant ID**: Identificatorul unic al comerciantului (oferit de MIA POS).
- **Secret Key**: Cheia secretă pentru autentificarea API (oferită de MIA POS).
- **Terminal ID**: Identificatorul terminalului dvs. (oferit de MIA POS).
- **API Base URL**: URL-ul endpoint-ului API pentru MIA POS. Acest URL trebuie obținut de la banca dvs. Asigurați-vă că testați pluginul mai întâi în mediul de testare al băncii.

### Parametri opționali

- **Setări status comandă: Plată în așteptare** - Statusul comenzii atunci când plata este în așteptare.
- **Setări status comandă: Plată reușită** - Statusul comenzii după finalizarea cu succes a plății.
- **Setări status comandă: Plată eșuată** - Statusul comenzii când plata eșuează.

## Testare

Pentru testare, trebuie să utilizați mediul de testare al băncii și un cont de test MIA POS. Contactați banca pentru a obține datele de testare.

1. Configurați pluginul folosind datele de testare.
2. Efectuați achiziții de test pentru a verifica procesul de plată.
3. Verificați dacă statusurile comenzilor sunt actualizate corect.
4. Verificați procesarea notificărilor de tip callback.

## Suport

Pentru suport și întrebări, contactați:
- Website: [https://finergy.md/](https://finergy.md/)
- Email: [info@finergy.md](mailto:info@finergy.md)
