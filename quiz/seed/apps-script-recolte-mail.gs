/**
 * Famiformation — pont entre la feuille de réponses du Google Form et le site.
 *
 * Ce script se colle SUR la feuille Google qui reçoit les réponses du formulaire.
 * Il expose UNIQUEMENT l'onglet « recolte de mail » (pas le 2e tableau), en JSON,
 * et seulement si l'appelant fournit le bon secret. La liste n'est donc jamais
 * publique : seul le site (qui connaît le secret) peut la lire.
 *
 * ── INSTALLATION (5 min) ─────────────────────────────────────────────────────
 * 1. Ouvre la feuille Google des réponses du Form.
 * 2. Menu  Extensions ▸ Apps Script.
 * 3. Efface le code par défaut, colle CE fichier en entier.
 * 4. Remplace la valeur de SECRET ci-dessous par un mot de passe long
 *    (ex. 30 caractères au hasard). GARDE-LE : il faudra le remettre sur Railway.
 * 5. Vérifie que NOM_ONGLET correspond EXACTEMENT au nom de ton onglet.
 * 6. Clique  Déployer ▸ Nouveau déploiement ▸ (roue) Application web.
 *      - Description : « pont recolte mail »
 *      - Exécuter en tant que : Moi
 *      - Qui a accès : Tout le monde
 *    Déployer ▸ Autoriser l'accès (accepte les permissions Google).
 * 7. Copie l'URL qui finit par /exec.
 *
 * ── CÔTÉ SITE (Railway) ──────────────────────────────────────────────────────
 * Ajoute deux variables d'environnement au service, puis redéploie :
 *   FORM_FEED_URL    = l'URL /exec copiée à l'étape 7
 *   FORM_FEED_SECRET = le même secret que ci-dessous
 *
 * Test rapide : ouvre  <URL>/exec?secret=TON_SECRET  dans le navigateur →
 * tu dois voir  {"ok":true,"total":…,"lignes":[…]}.  Sans le bon secret :
 * {"ok":false,"error":"unauthorized"}.
 */

const SECRET = 'admin';
const NOM_ONGLET = 'Récolte de mails - Famifromation - Google Sheets';

function doGet(e) {
  const out = (obj) =>
    ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);

  if (!e || !e.parameter || e.parameter.secret !== SECRET) {
    return out({ ok: false, error: 'unauthorized' });
  }
  const classeur = SpreadsheetApp.getActiveSpreadsheet();
  // 1) Nom exact, sinon 2) une languette dont le nom contient « mail » ou
  // « colte » (marche pour « Récolte de mails » sans se soucier des accents).
  // Sinon on renvoie la liste des vrais noms d'onglets pour aider au réglage.
  let sh = classeur.getSheetByName(NOM_ONGLET);
  if (!sh) {
    const feuilles = classeur.getSheets();
    sh = feuilles.filter((f) => {
      const n = f.getName().toLowerCase();
      return n.indexOf('mail') !== -1 || n.indexOf('colte') !== -1;
    })[0] || null;
    if (!sh) {
      return out({ ok: false, error: 'onglet_introuvable', onglets: feuilles.map((f) => f.getName()) });
    }
  }

  const data = sh.getDataRange().getValues();
  if (data.length < 2) return out({ ok: true, total: 0, lignes: [] });

  // Détection des colonnes par leur en-tête (1re ligne).
  const entetes = data.shift().map((h) => String(h).toLowerCase().trim());
  const trouve = (predicat) => entetes.findIndex(predicat);
  const iMail = trouve((h) => h.indexOf('mail') !== -1);
  const iPrenom = trouve((h) => h.indexOf('prénom') !== -1 || h.indexOf('prenom') !== -1);
  const iNom = trouve((h) => h.indexOf('nom') !== -1 && h.indexOf('prénom') === -1 && h.indexOf('prenom') === -1);

  const lignes = [];
  data.forEach((r) => {
    const email = iMail >= 0 ? String(r[iMail]).trim() : '';
    if (!email || email.indexOf('@') === -1) return;
    lignes.push({
      email: email,
      prenom: iPrenom >= 0 ? String(r[iPrenom]).trim() : '',
      nom: iNom >= 0 ? String(r[iNom]).trim() : '',
    });
  });
  return out({ ok: true, total: lignes.length, lignes: lignes });
}
