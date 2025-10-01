# Processo di build e release

## Prerequisiti

- PHP 8.2 con estensioni standard (`json`, `mbstring`, `curl`).
- Composer 2.x.
- Zip (`zip`) e `rsync` disponibili nel PATH.

## Comandi tipici

Incremento di versione (patch di default) e pacchetto ZIP:

```bash
bash build.sh --bump=patch
```

Impostare manualmente una versione specifica prima della build:

```bash
bash build.sh --set-version=1.2.3
```

Specificare un nome personalizzato per lo ZIP:

```bash
bash build.sh --set-version=1.2.3 --zip-name=fp-reservations.zip
```

Lo script `build.sh`:

1. Aggiorna la versione del plugin tramite `tools/bump-version.php`.
2. Esegue `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
3. Esegue `composer dump-autoload -o --classmap-authoritative`.
4. Copia i file runtime in `build/fp-restaurant-reservations/` escludendo asset e sorgenti di sviluppo.
5. Genera lo ZIP in `build/` con timestamp (o nome personalizzato).

L'output riporta versione finale e percorso dello ZIP generato.

## GitHub Action

Per generare automaticamente lo ZIP su GitHub Actions:

1. Effettua il commit delle modifiche e crea un tag nel formato `vX.Y.Z`.
2. Esegui `git push --tags`.
3. Il workflow **Build plugin ZIP** si occuperà di installare le dipendenze, copiare i file ammessi e pubblicare lo ZIP come artefatto chiamato `plugin-zip`.

Scarica l'artefatto dall'esecuzione del workflow per ottenere il pacchetto pronto all'uso.
