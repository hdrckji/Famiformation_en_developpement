# Prompt à coller dans Claude web (traduction des sous-titres)

Circuit complet pour les vidéos, sans aucun appel IA facturé par le site :

1. **Téléverser** les vidéos (étape 1 de la page d'import) et attendre la fin du
   transcodage — la ligne ne doit plus afficher « transcodage… ».
2. **Whisper chez toi** (OpenAI) sur le MP4 → tu obtiens un `.srt` français.
3. **Ce prompt** dans Claude web avec le `.srt` → tu obtiens le `.srt` néerlandais.
4. **Importer les deux** (étape 2 ter), en nommant les fichiers avec le code de
   la vidéo entre crochets.

## Nommage des fichiers — c'est ce qui fait le routage

Le code entre crochets est l'identifiant de la vidéo ; il est déjà dans le nom
du MP4 d'origine. Reprends-le tel quel :

```
Caisse [0v6VW-TlFfs].srt          → piste d'origine
Caisse [0v6VW-TlFfs]_nl.srt       → piste néerlandaise
```

Sans crochets, l'import ne sait pas à quelle vidéo rattacher le fichier et le
refuse. Le suffixe `_nl` (ou `.nl`, ou `-nl`) est le seul marqueur de langue :
tout fichier sans ce suffixe est traité comme la langue d'origine.

## Commande Whisper (rappel)

```sh
curl https://api.openai.com/v1/audio/transcriptions \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -F file=@"Caisse [0v6VW-TlFfs].mp4" \
  -F model=whisper-1 \
  -F response_format=srt \
  -o "Caisse [0v6VW-TlFfs].srt"
```

Whisper accepte jusqu'à 25 Mo par fichier. Tes vidéos font 40 à 190 Mo : extrais
d'abord la piste audio, elle passe largement sous la limite.

```sh
ffmpeg -i "Caisse [0v6VW-TlFfs].mp4" -vn -ac 1 -ar 16000 -b:a 64k "Caisse [0v6VW-TlFfs].mp3"
```

---

## À coller (avec le .srt en pièce jointe)

````
Tu reçois un fichier de sous-titres au format SRT, transcrit depuis une vidéo de
formation interne pour Famiflora, une jardinerie belge.

Traduis-le en néerlandais de Belgique et renvoie UNIQUEMENT le SRT traduit,
sans aucun texte avant ou après, sans bloc de code.

RÈGLES ABSOLUES
- Ne touche JAMAIS aux timecodes : recopie chaque ligne "00:00:01,000 -->
  00:00:03,000" à l'identique, au caractère près.
- Garde EXACTEMENT le même nombre de séquences, dans le même ordre, avec la
  même numérotation. Ne fusionne pas deux séquences, n'en supprime aucune,
  n'en ajoute aucune.
- Ne traduis que les lignes de texte.
- Si une séquence tient sur deux lignes dans l'original, garde deux lignes.

QUALITÉ DE LA TRADUCTION
- Néerlandais de Belgique, tutoiement, ton naturel et parlé (c'est de l'oral).
- Corrige au passage les erreurs manifestes de transcription de Whisper :
  mots mal entendus, noms propres déformés. Le contexte est une jardinerie
  (plantes, jardin, caisse, magasin, clients).
- Garde les noms propres et les noms de produits tels quels.
- Une séquence de sous-titre doit rester courte et lisible à l'écran : si la
  traduction néerlandaise est nettement plus longue, resserre la formulation
  plutôt que de déborder.
````

---

## Contrôle à l'import

La page compte les séquences des deux pistes et te l'affiche. **Si les deux
nombres diffèrent, la traduction a fusionné ou sauté des séquences** : le
décalage se verra à l'écran. Redemande la traduction en insistant sur la règle
du nombre de séquences.

Seule la piste d'origine alimente le champ `transcript` (le texte brut qui sert
à enrichir un éventuel quiz) — la traduction ne sert qu'à l'affichage.
