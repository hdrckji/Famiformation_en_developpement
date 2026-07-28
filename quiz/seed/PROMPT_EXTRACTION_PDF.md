# Prompt à coller dans Claude web (avec le ZIP des PDF)

Ce prompt fait produire à Claude un **seul fichier JSON** contenant les fiches de
tous les PDF, en français ET en néerlandais. Ce fichier s'importe ensuite dans
Famiformation via **Paramètres → Contenu → Import par lot → « Importer un JSON »**.

Le schéma ci-dessous correspond exactement à `aiSanitizeBlocks()`
(`public/includes/ai_uniformise.php`) : tout bloc non conforme est ignoré
silencieusement à l'import, alors ne l'invente pas.

---

## À coller (avec le ZIP en pièce jointe)

````
Tu reçois un ZIP contenant des PDF de formation interne pour Famiflora, une
jardinerie belge. Pour CHAQUE PDF, produis une fiche web structurée, en
français ET en néerlandais.

Réponds UNIQUEMENT avec un fichier JSON valide (aucun texte autour), au format :

{
  "version": 1,
  "documents": [
    {
      "file": "nom-exact-du-fichier.pdf",
      "lang": "fr",
      "blocks":    [ ... la fiche dans la langue du document ... ],
      "blocks_nl": [ ... la MÊME fiche traduite dans l'autre langue ... ]
    }
  ]
}

RÈGLES GÉNÉRALES
- "file" doit être le nom EXACT du fichier dans le ZIP, extension comprise.
- "lang" est la langue DÉTECTÉE du document : "fr" ou "nl".
- "blocks" est la fiche dans la langue du document.
- "blocks_nl" est sa traduction : si lang="fr" traduis en néerlandais, si
  lang="nl" traduis en français. Même structure, mêmes blocs, dans le même ordre.
- Néerlandais de Belgique, tutoiement, ton chaleureux (univers jardinerie).
- Fidélité absolue au fond : n'invente AUCUNE donnée absente du document.
- Supprime les numéros de page, en-têtes et pieds de page répétés.
- Corrige les fautes d'orthographe et de grammaire au passage.
- Si un point du document est ambigu ou contradictoire, garde-le et ajoute un
  champ "fix" (une phrase expliquant le doute) sur le bloc concerné.

TYPES DE BLOCS AUTORISÉS — n'en utilise AUCUN autre

{"type":"hero","title":"Titre principal","subtitle":"Sous-titre"}
{"type":"section","title":"Titre de section","align":"left|center|right"}
{"type":"text","text":"Un paragraphe.","align":"left|center|right","fix":"doute éventuel"}
{"type":"list","items":["Premier point","Deuxième point"]}
{"type":"steps","items":[{"title":"Étape 1","desc":"Description"}]}
{"type":"callout","style":"info|tip|warning","title":"Titre","text":"Message","fix":"doute éventuel"}
{"type":"keyfigures","items":[{"value":"30 %","label":"Ce que ça mesure"}]}
{"type":"quote","text":"Une citation du document","align":"left|center|right"}

- "align" et "fix" sont facultatifs : ne les mets que s'ils servent.
- N'utilise JAMAIS le type "image" : les illustrations sont gérées
  séparément par le site, pas par toi.
- "section" exige un "title" non vide, "text" et "quote" un "text" non vide,
  "list"/"steps"/"keyfigures" au moins un item — sinon le bloc est jeté.

STRUCTURE ATTENDUE D'UNE FICHE
1. Un "hero" en ouverture (titre + sous-titre accrocheur).
2. Le contenu découpé en "section" suivies de "text", "list", "steps"…
3. Les consignes de sécurité ou points de vigilance en "callout" style "warning".
4. Les astuces en "callout" style "tip".
5. Les chiffres marquants en "keyfigures".

Si le ZIP est trop gros pour être traité en une fois, traite les documents par
paquets et donne-moi un JSON par paquet — je les importerai l'un après l'autre.
````

---

## Ce que ce circuit NE fait pas

- **Pas d'images.** `aiUniformisePdf()` extrait les illustrations du PDF avec
  `pdfimages` côté serveur ; Claude web n'y a pas accès. Les fiches importées
  par JSON sont donc en texte seul. Les images peuvent être rajoutées ensuite à
  la main depuis l'éditeur de module.
- **Pas de quiz.** `aiGenerateQuiz()` reste un appel API séparé.
- Le PDF source doit quand même être téléversé sur le volume (onglet « 1.
  Téléverser ») pour rester téléchargeable ; l'import JSON ne remplit que le
  contenu de la fiche.
