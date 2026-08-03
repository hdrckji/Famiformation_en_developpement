<?php
// ============================================================
// La Panne — inscription à Famiformation.
//
// Reprend le parcours de emails/index.html à l'identique : porte avec compte à
// rebours, ticket glace qui se dérobe, « C'est parti », écran de bienvenue,
// puis la plante qui fleurit et la récompense.
//
// SEULE DIFFÉRENCE : là où la page d'origine renvoyait vers un Google Form,
// celle-ci ouvre son propre formulaire, dans la page, et enregistre chez nous.
// Le reste du chemin est volontairement identique.
// ============================================================

require_once __DIR__ . '/_lapanne.php';

$etat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $resultat = lapanneEnregistrer(
        $db,
        $_POST['nom'] ?? '',
        $_POST['prenom'] ?? '',
        $_POST['email'] ?? ''
    );
    if ($resultat['ok']) {
        // Redirection après succès : sans elle, un rafraîchissement renverrait
        // le formulaire une seconde fois.
        header('Location: index.php?ok=' . urlencode($resultat['etat']));
        exit();
    }
    $etat = $resultat['etat'];
}

if ($etat === '' && isset($_GET['ok'])) {
    $etat = (string) $_GET['ok'];
}

$succes = in_array($etat, ['ajoute', 'deja_inscrit'], true);
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Famiformation — La Panne</title>
<link rel="shortcut icon" type="image/x-icon" href="../../favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  /* Charte reprise de emails/index.html — les deux pages doivent se ressembler. */
  :root{--green:#2e7d46;--deep:#1f5c34;--leaf:#7cb342;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--red:#a8341f;}
  *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
  body{background:#eef4ea;color:var(--ink);min-height:100vh;}
  .leafbg{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none;}
  .leafbg svg{position:absolute;opacity:.06;color:#2e7d46;}
  .wrap{position:relative;z-index:2;max-width:600px;margin:0 auto;padding:22px 16px 46px;}
  .langtoggle{position:fixed;top:14px;right:14px;z-index:80;background:#fff;border-radius:22px;box-shadow:0 3px 12px rgba(0,0,0,.14);display:flex;overflow:hidden;}
  .langtoggle button{width:auto;margin:0;padding:8px 15px;border:0;background:transparent;color:var(--muted);font-weight:700;font-size:13px;cursor:pointer;border-radius:0;}
  .langtoggle button.active{background:var(--deep);color:#fff;}

  .plantbox{width:96px;height:96px;margin:0 auto 6px;}
  .plantbox svg{width:100%;height:100%;display:block;overflow:visible;}
  .pstage{display:none;} .pstage.on{display:block;}
  @keyframes popIn{0%{transform:scale(.3);opacity:0;}70%{transform:scale(1.12);}100%{transform:scale(1);opacity:1;}}
  .pstage.on g{transform-origin:60px 95px;animation:popIn .7s ease-out;}
  @keyframes sway{0%,100%{transform:rotate(-2deg);}50%{transform:rotate(2deg);}}
  .sway{transform-origin:60px 95px;animation:sway 3.2s ease-in-out infinite;}

  .gate{position:fixed;inset:0;z-index:60;background:linear-gradient(160deg,#eef6ec,#d9ecd4);display:flex;align-items:center;justify-content:center;padding:20px;overflow:auto;}
  .card-g{background:#fff;border-radius:22px;box-shadow:0 14px 44px rgba(20,55,38,.18);max-width:470px;width:100%;padding:26px 24px;text-align:center;}
  .gate h2{color:var(--deep);font-weight:800;font-size:23px;line-height:1.15;}
  .rocket{margin-top:5px;font-size:13.5px;font-weight:700;color:var(--green);}
  .gate p.lead{color:#3f4a41;font-size:15px;line-height:1.55;margin-top:12px;} .gate p.lead b{color:var(--deep);}
  details.more{margin-top:12px;text-align:left;background:var(--mint);border:1px solid var(--mint-line);border-radius:12px;padding:0 14px;}
  details.more summary{list-style:none;cursor:pointer;padding:11px 0;font-weight:700;font-size:13.5px;color:var(--deep);text-align:center;}
  details.more summary::-webkit-details-marker{display:none;}
  details.more .mtext{padding:0 0 13px;font-size:13px;line-height:1.55;color:#4a564c;}

  .cd{margin-top:16px;}
  .cdlabel{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);}
  .cdgrid{margin-top:8px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
  .cdbox{background:var(--mint);border:1px solid var(--mint-line);border-radius:12px;padding:10px 4px;}
  .cdnum{font-weight:900;font-size:24px;color:var(--deep);line-height:1;}
  .cdunit{margin-top:3px;font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;}

  button{border:0;border-radius:12px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;width:100%;}
  .b-main{background:var(--deep);color:#fff;margin-top:16px;}
  .b-ghost{background:transparent;color:var(--muted);margin-top:6px;font-weight:600;}

  .welcome{position:fixed;inset:0;z-index:70;background:linear-gradient(160deg,#eef6ec,#d9ecd4);display:none;align-items:center;justify-content:center;text-align:center;padding:20px;}
  .welcome .wc{animation:popIn .6s ease-out;}
  .welcome .big{font-size:52px;}
  .welcome h3{color:var(--deep);font-weight:900;font-size:30px;margin-top:6px;}
  .welcome p{color:#3f4a41;font-size:15px;margin-top:6px;}

  .cf{position:fixed;top:-14px;z-index:75;border-radius:3px;pointer-events:none;animation:fall linear forwards;}
  @keyframes fall{to{transform:translateY(110vh) rotate(720deg);}}

  @keyframes dropIn{0%{transform:translateY(-70vh) rotate(-14deg);}70%{transform:translateY(0) rotate(6deg);}85%{transform:translateY(-12px) rotate(-3deg);}100%{transform:translateY(0) rotate(0);}}
  .reward{position:fixed;inset:0;z-index:72;background:rgba(20,45,32,.45);display:none;align-items:center;justify-content:center;padding:24px;}
  .reward .rc{background:#fff;border-radius:20px;padding:24px 22px;max-width:400px;width:100%;text-align:center;box-shadow:0 16px 44px rgba(0,0,0,.3);animation:popIn .4s ease-out;}
  .reward .rticket{width:150px;margin:4px auto 10px;animation:dropIn .9s ease-out;}
  .reward .rticket svg{width:100%;display:block;filter:drop-shadow(0 6px 12px rgba(0,0,0,.18));}
  .reward h4{color:var(--deep);font-weight:900;font-size:20px;}
  .reward p{color:#4a564c;font-size:13.5px;margin-top:6px;line-height:1.5;} .reward p b{color:var(--deep);}
  .reward .rbtn{display:block;margin-top:14px;background:var(--deep);color:#fff;border-radius:12px;padding:14px;font-size:15px;font-weight:700;text-decoration:none;cursor:pointer;}

  .content{display:none;}
  .minihead{text-align:center;margin-bottom:10px;}
  .minihead img{width:54px;height:54px;background:#fff;border-radius:50%;padding:8px;box-shadow:0 5px 16px rgba(31,92,52,.14);object-fit:contain;}
  a.bigcta{display:block;text-decoration:none;background:linear-gradient(150deg,#3f9d57,#1f5c34);color:#fff;border-radius:22px;
    padding:24px 20px 22px;text-align:center;box-shadow:0 12px 30px rgba(31,92,52,.28);cursor:pointer;}
  a.bigcta:active{transform:scale(.99);}
  .bigcta .btitle{font-weight:900;font-size:30px;line-height:1.06;letter-spacing:-.01em;}
  .bigcta .bsub{font-size:14.5px;color:#eafaef;margin-top:8px;}
  .bigcta .bbtn{display:inline-block;margin-top:14px;background:#fff;color:var(--deep);font-weight:800;font-size:15px;padding:12px 24px;border-radius:30px;}
  .bbtn svg.tks{height:24px;width:auto;vertical-align:-6px;margin:0 3px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.18));}
  details.small{margin-top:10px;background:#fff;border:1px solid var(--mint-line);border-radius:12px;padding:0 14px;}
  .grid2{margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:stretch;}
  a.card{display:flex;flex-direction:column;align-items:center;text-align:center;background:#fff;border:1px solid var(--mint-line);border-radius:16px;padding:18px 12px;text-decoration:none;box-shadow:0 5px 16px rgba(20,55,38,.08);}
  a.card:active{transform:scale(.97);}
  .emoji{font-size:34px;line-height:1;}
  .ctitle{font-weight:800;font-size:14.5px;color:var(--deep);margin-top:9px;line-height:1.2;}
  .cdesc{font-size:11.5px;color:var(--muted);margin-top:5px;line-height:1.35;flex:1;}
  .tag{margin-top:8px;font-size:11px;font-weight:700;color:var(--green);} .tag::after{content:" \2192";}
  .foot{text-align:center;font-size:12.5px;color:var(--muted);margin-top:24px;line-height:1.5;} .foot b{color:var(--deep);}

  @keyframes bob{0%,100%{transform:translateY(0) rotate(-3deg);}50%{transform:translateY(-7px) rotate(3deg);}}
  #ticket{position:fixed;z-index:65;width:112px;right:14px;bottom:18px;cursor:pointer;animation:bob 2.2s ease-in-out infinite;transition:left .45s cubic-bezier(.3,1.6,.5,1), top .45s cubic-bezier(.3,1.6,.5,1);filter:drop-shadow(0 0 14px rgba(255,207,51,.55));}
  #ticket svg{width:100%;display:block;filter:drop-shadow(0 5px 10px rgba(0,0,0,.2));}
  .tbubble{position:fixed;z-index:66;background:#fff;border:1px solid var(--mint-line);border-radius:14px;padding:7px 12px;font-weight:800;font-size:14px;color:var(--deep);
    box-shadow:0 4px 14px rgba(0,0,0,.15);pointer-events:none;animation:popIn .3s ease-out;max-width:230px;line-height:1.3;}

  /* --- Le formulaire, qui remplace le Google Form d'origine --- */
  .formscreen{position:fixed;inset:0;z-index:73;background:linear-gradient(160deg,#eef6ec,#d9ecd4);display:none;align-items:flex-start;justify-content:center;padding:20px;overflow:auto;}
  .fc{background:#fff;border-radius:22px;box-shadow:0 14px 44px rgba(20,55,38,.18);max-width:440px;width:100%;padding:26px 24px;margin:auto;}
  .fc h3{color:var(--deep);font-weight:900;font-size:22px;text-align:center;line-height:1.15;}
  .fc .fsub{color:var(--muted);font-size:13.5px;text-align:center;margin-top:7px;line-height:1.5;}
  .fc label{display:block;margin-top:16px;font-weight:700;font-size:13.5px;color:var(--deep);text-align:left;}
  .fc input{width:100%;margin-top:6px;padding:12px 13px;border:1px solid var(--mint-line);border-radius:12px;font-size:16px;background:var(--mint);}
  .fc input:focus{outline:2px solid var(--leaf);outline-offset:1px;background:#fff;}
  .fc .b-main{margin-top:20px;}
  .fc .err{margin-top:14px;background:#fdecea;border:1px solid #f6cdc7;color:var(--red);padding:11px 13px;border-radius:12px;font-size:13.5px;font-weight:600;text-align:center;}
  .fc .note{margin-top:14px;font-size:11.5px;color:var(--muted);line-height:1.5;text-align:center;}

  /* --- L'écran de fin, avec l'indice du ticket glace --- */
  .done{position:fixed;inset:0;z-index:74;background:linear-gradient(160deg,#eef6ec,#d9ecd4);display:none;align-items:center;justify-content:center;padding:22px;overflow:auto;}
  .dc{background:#fff;border-radius:22px;box-shadow:0 14px 44px rgba(20,55,38,.18);max-width:430px;width:100%;padding:28px 24px;text-align:center;margin:auto;}
  .dc .rticket{width:150px;margin:2px auto 12px;animation:dropIn .9s ease-out;}
  .dc .rticket svg{width:100%;display:block;filter:drop-shadow(0 6px 12px rgba(0,0,0,.18));}
  .dc h3{color:var(--deep);font-weight:900;font-size:23px;line-height:1.15;}
  .dc p{color:#4a564c;font-size:14px;margin-top:9px;line-height:1.55;} .dc p b{color:var(--deep);}
</style></head>
<body>
  <!-- Dégradé vert du ticket : défini une seule fois ici, et réutilisé par les
       trois exemplaires (accueil, récompense, écran de fin). Le sortir des SVG
       évite d'avoir le même identifiant en triple, et surtout garantit qu'il
       reste disponible même quand l'écran qui le portait est masqué. -->
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs><linearGradient id="tg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#4bb063"/><stop offset="1" stop-color="#1f5c34"/></linearGradient></defs>
  </svg>

  <div class="langtoggle">
    <button id="btnFR" class="active" onclick="setLang('fr')">FR</button>
    <button id="btnNL" onclick="setLang('nl')">NL</button>
  </div>
  <div class="leafbg">
    <svg style="width:200px;top:-30px;left:-40px;transform:rotate(20deg);" viewBox="0 0 100 100"><path d="M50 5C25 20 16 48 28 74c6 13 15 21 22 26 7-5 16-13 22-26C84 48 75 20 50 5z" fill="currentColor"/></svg>
    <svg style="width:170px;bottom:40px;right:-40px;transform:rotate(200deg);" viewBox="0 0 100 100"><path d="M50 5C25 20 16 48 28 74c6 13 15 21 22 26 7-5 16-13 22-26C84 48 75 20 50 5z" fill="currentColor"/></svg>
  </div>

  <!-- ECRAN 1 : la porte, avec le compte a rebours -->
  <div class="gate" id="gate">
    <div class="card-g">
      <div class="plantbox">
        <svg viewBox="0 0 120 100">
          <ellipse cx="60" cy="92" rx="34" ry="7" fill="#d9ecd4"/>
          <g class="pstage on"><g class="sway">
            <ellipse cx="60" cy="84" rx="11" ry="14" fill="#a8794a"/>
            <path d="M60 72 c-3 4 -3 9 0 12 3 -3 3 -8 0 -12z" fill="#8a5f36"/>
          </g></g>
        </svg>
      </div>
      <h2 id="g_title"></h2>
      <div class="rocket" id="g_rocket"></div>
      <p class="lead" id="g_body"></p>
      <details class="more">
        <summary id="g_more_label"></summary>
        <div class="mtext" id="g_more_text"></div>
      </details>
      <div class="cd" id="cdbloc">
        <div class="cdlabel" id="cd_label"></div>
        <div class="cdgrid">
          <div class="cdbox"><div class="cdnum" id="cd_d">–</div><div class="cdunit" id="cd_ud"></div></div>
          <div class="cdbox"><div class="cdnum" id="cd_h">–</div><div class="cdunit" id="cd_uh"></div></div>
          <div class="cdbox"><div class="cdnum" id="cd_m">–</div><div class="cdunit" id="cd_um"></div></div>
          <div class="cdbox"><div class="cdnum" id="cd_s">–</div><div class="cdunit" id="cd_us"></div></div>
        </div>
      </div>
      <button class="b-main" id="g_btn" onclick="enter()"></button>
      <button class="b-ghost" id="g_quit" onclick="quit()"></button>
    </div>
  </div>

  <!-- BIENVENUE -->
  <div class="welcome" id="welcome">
    <div class="wc">
      <div class="plantbox" style="width:110px;height:110px;">
        <svg viewBox="0 0 120 100">
          <ellipse cx="60" cy="92" rx="34" ry="7" fill="#cfe6c9"/>
          <g class="sway">
            <path d="M60 90 V60" stroke="#3f9d57" stroke-width="5" stroke-linecap="round"/>
            <path d="M60 68 C46 66 40 54 42 46 C54 46 62 54 60 68z" fill="#7cb342"/>
            <path d="M60 62 C74 60 80 48 78 40 C66 40 58 48 60 62z" fill="#3f9d57"/>
          </g>
        </svg>
      </div>
      <div class="big">🎉</div>
      <h3 id="w_title"></h3>
      <p id="w_sub"></p>
    </div>
  </div>

  <!-- RECOMPENSE : le ticket glace tombe du haut -->
  <div class="reward" id="reward">
    <div class="rc">
      <div class="rticket"><svg viewBox="0 0 170 104" xmlns="http://www.w3.org/2000/svg">
  <rect x="2" y="2" width="166" height="100" rx="12" fill="url(#tg)" stroke="#164a2b" stroke-width="2"/>
  <rect x="10" y="12" width="106" height="80" rx="7" fill="#fff"/>
  <text x="24" y="52" font-family="Inter,Arial,sans-serif" font-size="34" font-weight="900" fill="#1c1c1c" font-style="italic">1x</text>
  <text x="18" y="72" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATUIT</text>
  <text x="22" y="88" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATIS</text>
  <g>
    <path d="M118 60 L152 60 L135 98 Z" fill="#e8a94a" stroke="#8a5f2a" stroke-width="1.5"/>
    <path d="M122 66 L148 66 M126 74 L144 74 M130 82 L140 82 M126 60 L138 92 M144 60 L133 92" stroke="#8a5f2a" stroke-width="1.2"/>
    <path d="M116 58 C110 40 118 26 128 24 C126 12 146 8 150 20 C160 18 166 30 158 38 C166 44 158 58 150 56 Z" fill="#fdf6e6" stroke="#caa96a" stroke-width="1.4"/>
    <circle cx="130" cy="40" r="2.6" fill="#2a2a2a"/><circle cx="146" cy="40" r="2.6" fill="#2a2a2a"/>
    <circle cx="126" cy="47" r="2.4" fill="#ff9d8a" opacity=".6"/><circle cx="150" cy="47" r="2.4" fill="#ff9d8a" opacity=".6"/>
    <path d="M131 48 Q138 55 145 48" fill="none" stroke="#2a2a2a" stroke-width="2" stroke-linecap="round"/>
    <path d="M128 64 C124 60 128 54 133 57 C134 53 141 53 142 57 C147 54 151 60 147 64 C144 68 132 68 128 64z" fill="#e5533c"/>
  </g>
</svg></div>
      <h4 id="r_title"></h4>
      <p id="r_sub"></p>
      <a id="r_btn" class="rbtn" onclick="openForm()"></a>
    </div>
  </div>

  <!-- ECRAN 2 : le contenu -->
  <div class="content" id="content">
    <div class="wrap">
      <div class="minihead"><img src="../../logo.png" alt="Famiflora"></div>
      <a class="bigcta" id="lnkForm" onclick="return goForm(event)">
        <div class="plantbox" id="ctaplant">
          <svg viewBox="0 0 120 100">
            <ellipse cx="60" cy="92" rx="34" ry="7" fill="rgba(255,255,255,.25)"/>
            <g class="pstage on" id="st_sprout"><g class="sway">
              <path d="M60 90 V58" stroke="#d7f59a" stroke-width="5" stroke-linecap="round"/>
              <path d="M60 68 C46 66 40 54 42 46 C54 46 62 54 60 68z" fill="#b6e07a"/>
              <path d="M60 60 C74 58 80 46 78 38 C66 38 58 46 60 60z" fill="#eafaef"/>
            </g></g>
            <g class="pstage" id="st_flower"><g class="sway">
              <path d="M60 90 V52" stroke="#d7f59a" stroke-width="5" stroke-linecap="round"/>
              <path d="M60 74 C46 72 40 60 42 52 C54 52 62 60 60 74z" fill="#b6e07a"/>
              <path d="M60 66 C74 64 80 52 78 44 C66 44 58 52 60 66z" fill="#eafaef"/>
              <g>
                <circle cx="60" cy="34" r="7" fill="#ffcf33"/>
                <ellipse cx="60" cy="20" rx="6" ry="9" fill="#fff"/><ellipse cx="60" cy="48" rx="6" ry="9" fill="#fff"/>
                <ellipse cx="46" cy="34" rx="9" ry="6" fill="#fff"/><ellipse cx="74" cy="34" rx="9" ry="6" fill="#fff"/>
                <ellipse cx="50" cy="24" rx="7" ry="6" fill="#fff" transform="rotate(-45 50 24)"/>
                <ellipse cx="70" cy="24" rx="7" ry="6" fill="#fff" transform="rotate(45 70 24)"/>
                <ellipse cx="50" cy="44" rx="7" ry="6" fill="#fff" transform="rotate(45 50 44)"/>
                <ellipse cx="70" cy="44" rx="7" ry="6" fill="#fff" transform="rotate(-45 70 44)"/>
              </g>
            </g></g>
          </svg>
        </div>
        <div class="btitle" id="cta_title"></div>
        <div class="bsub" id="cta_sub"></div>
        <div class="bbtn" id="cta_btn"></div>
      </a>
      <details class="more small">
        <summary id="m_label"></summary>
        <div class="mtext" id="m_text"></div>
      </details>
      <div class="grid2">
        <a class="card" id="lnkPres" href="../presentation.html">
          <div class="emoji">🔎</div><div class="ctitle" id="t1"></div><div class="cdesc" id="d1"></div><span class="tag" id="tag1"></span>
        </a>
        <a class="card" id="lnkCreer" href="../creer-email.html">
          <div class="emoji">🌱</div><div class="ctitle" id="t3"></div><div class="cdesc" id="d3"></div><span class="tag" id="tag3"></span>
        </a>
      </div>
      <p class="foot" id="c_foot"></p>
    </div>
  </div>

  <!-- LE FORMULAIRE — remplace le Google Form de la page d'origine -->
  <div class="formscreen" id="formscreen">
    <div class="fc">
      <h3 id="f_title"></h3>
      <p class="fsub" id="f_sub"></p>
      <div class="err" id="f_err" style="display:none;"></div>
      <form method="POST">
        <?= csrfField() ?>
        <label for="nom" id="l_nom"></label>
        <input id="nom" name="nom" type="text" maxlength="120" required autocomplete="family-name"
               value="<?= e($_POST['nom'] ?? '') ?>">
        <label for="prenom" id="l_prenom"></label>
        <input id="prenom" name="prenom" type="text" maxlength="120" required autocomplete="given-name"
               value="<?= e($_POST['prenom'] ?? '') ?>">
        <label for="email" id="l_email"></label>
        <input id="email" name="email" type="email" maxlength="190" required autocomplete="email"
               placeholder="prenom@famiflora.be" value="<?= e($_POST['email'] ?? '') ?>">
        <button class="b-main" type="submit" id="f_btn"></button>
      </form>
      <p class="note" id="f_note"></p>
    </div>
  </div>

  <!-- ECRAN DE FIN : l'indice pour recuperer la glace -->
  <div class="done" id="done">
    <div class="dc">
      <div class="rticket"><svg viewBox="0 0 170 104" xmlns="http://www.w3.org/2000/svg">
  <rect x="2" y="2" width="166" height="100" rx="12" fill="url(#tg)" stroke="#164a2b" stroke-width="2"/>
  <rect x="10" y="12" width="106" height="80" rx="7" fill="#fff"/>
  <text x="24" y="52" font-family="Inter,Arial,sans-serif" font-size="34" font-weight="900" fill="#1c1c1c" font-style="italic">1x</text>
  <text x="18" y="72" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATUIT</text>
  <text x="22" y="88" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATIS</text>
  <g>
    <path d="M118 60 L152 60 L135 98 Z" fill="#e8a94a" stroke="#8a5f2a" stroke-width="1.5"/>
    <path d="M122 66 L148 66 M126 74 L144 74 M130 82 L140 82 M126 60 L138 92 M144 60 L133 92" stroke="#8a5f2a" stroke-width="1.2"/>
    <path d="M116 58 C110 40 118 26 128 24 C126 12 146 8 150 20 C160 18 166 30 158 38 C166 44 158 58 150 56 Z" fill="#fdf6e6" stroke="#caa96a" stroke-width="1.4"/>
    <circle cx="130" cy="40" r="2.6" fill="#2a2a2a"/><circle cx="146" cy="40" r="2.6" fill="#2a2a2a"/>
    <path d="M131 48 Q138 55 145 48" fill="none" stroke="#2a2a2a" stroke-width="2" stroke-linecap="round"/>
    <path d="M128 64 C124 60 128 54 133 57 C134 53 141 53 142 57 C147 54 151 60 147 64 C144 68 132 68 128 64z" fill="#e5533c"/>
  </g>
</svg></div>
      <h3 id="dn_title"></h3>
      <p id="dn_sub"></p>
      <p id="dn_hint"></p>
    </div>
  </div>

  <div id="ticket" onclick="catchTicket(event)"><svg viewBox="0 0 170 104" xmlns="http://www.w3.org/2000/svg">
  <rect x="2" y="2" width="166" height="100" rx="12" fill="url(#tg)" stroke="#164a2b" stroke-width="2"/>
  <rect x="10" y="12" width="106" height="80" rx="7" fill="#fff"/>
  <text x="24" y="52" font-family="Inter,Arial,sans-serif" font-size="34" font-weight="900" fill="#1c1c1c" font-style="italic">1x</text>
  <text x="18" y="72" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATUIT</text>
  <text x="22" y="88" font-family="Inter,Arial,sans-serif" font-size="15" font-weight="900" fill="#1c1c1c" font-style="italic">GRATIS</text>
  <g>
    <path d="M118 60 L152 60 L135 98 Z" fill="#e8a94a" stroke="#8a5f2a" stroke-width="1.5"/>
    <path d="M122 66 L148 66 M126 74 L144 74 M130 82 L140 82 M126 60 L138 92 M144 60 L133 92" stroke="#8a5f2a" stroke-width="1.2"/>
    <path d="M116 58 C110 40 118 26 128 24 C126 12 146 8 150 20 C160 18 166 30 158 38 C166 44 158 58 150 56 Z" fill="#fdf6e6" stroke="#caa96a" stroke-width="1.4"/>
    <circle cx="130" cy="40" r="2.6" fill="#2a2a2a"/><circle cx="146" cy="40" r="2.6" fill="#2a2a2a"/>
    <circle cx="126" cy="47" r="2.4" fill="#ff9d8a" opacity=".6"/><circle cx="150" cy="47" r="2.4" fill="#ff9d8a" opacity=".6"/>
    <path d="M131 48 Q138 55 145 48" fill="none" stroke="#2a2a2a" stroke-width="2" stroke-linecap="round"/>
    <path d="M128 64 C124 60 128 54 133 57 C134 53 141 53 142 57 C147 54 151 60 147 64 C144 68 132 68 128 64z" fill="#e5533c"/>
  </g>
</svg></div>

<script>
  /* ⚙️ RÉGLAGES — la seule zone à modifier au quotidien.
     launchDate : date du lancement à La Panne. Si elle est passée (ou vide),
     le compte à rebours se masque tout seul, la page reste utilisable. */
  const CONFIG = {
    launchDate: "",
    quitUrl:    "https://www.famiflora.be"
  };

  /* État renvoyé par le serveur après un envoi du formulaire. */
  const ETAT = <?= json_encode($etat, JSON_UNESCAPED_UNICODE) ?>;
  const SUCCES = <?= $succes ? 'true' : 'false' ?>;

  const T = {
    g_title:{fr:"Inscription à Famiformation", nl:"Inschrijving voor Famiformation"},
    g_rocket:{fr:"Famiflora La Panne, tout le monde à bord 🚀", nl:"Famiflora De Panne, iedereen aan boord 🚀"},
    g_body:{fr:"Pour vous inscrire, il nous faut juste <b>votre adresse e-mail</b> — c'est réglé en 10 secondes ⏱️",
             nl:"Om u in te schrijven hebben we enkel <b>uw e-mailadres</b> nodig — in 10 seconden geregeld ⏱️"},
    g_more_label:{fr:"En savoir plus", nl:"Meer weten"},
    g_more_text:{fr:"🚀 Famiformation débarque à La Panne ! Famiformation, c'est quoi ? Vous le découvrirez en poursuivant… 😉 Pour préparer votre compte, on a juste besoin de votre adresse e-mail — c'est tout. Tout le monde chez Famiflora est de la partie 💪. Dès que votre compte est prêt, vous recevrez un e-mail avec votre identifiant et tout ce qu'il faut pour créer votre mot de passe et vous connecter. À très vite !",
             nl:"🚀 Famiformation landt in De Panne! Wat is Famiformation? Dat ontdekt u zo meteen… 😉 Om uw account klaar te zetten, hebben we enkel uw e-mailadres nodig — meer niet. Iedereen bij Famiflora doet mee 💪. Zodra uw account klaar is, ontvangt u een e-mail met uw gebruikersnaam en alles wat u nodig heeft om uw wachtwoord aan te maken en in te loggen. Tot snel!"},
    cd_label:{fr:"Lancement dans", nl:"Lancering over"},
    cd_ud:{fr:"jours", nl:"dagen"}, cd_uh:{fr:"heures", nl:"uren"}, cd_um:{fr:"min", nl:"min"}, cd_us:{fr:"sec", nl:"sec"},
    g_btn:{fr:"C'est parti →", nl:"Daar gaan we →"},
    g_quit:{fr:"Quitter", nl:"Verlaten"},
    w_title:{fr:"Bienvenue !", nl:"Welkom!"},
    w_sub:{fr:"Votre graine est plantée…", nl:"Uw zaadje is geplant…"},
    r_title:{fr:"Bravo ! 🥁", nl:"Proficiat! 🥁"},
    r_sub:{fr:"Un ticket glace vous attend 🍦<br>Attendez ! L'indice pour le récupérer se cache à la fin du formulaire 😉",
            nl:"Er wacht u een ijsjesticket 🍦<br>Wacht even! De aanwijzing om hem af te halen, verstopt zich op het einde van het formulier 😉"},
    r_btn:{fr:"J'y vais, ma glace m'attend 🍦 →", nl:"Ik ga ervoor, mijn ijsje wacht 🍦 →"},
    cta_title:{fr:"Je m'inscris !", nl:"Ik schrijf me in!"},
    cta_sub:{fr:"Votre e-mail suffit — 10 secondes ⏱️", nl:"Uw e-mailadres volstaat — 10 seconden ⏱️"},
    cta_btn:{fr:"Remplir le formulaire →", nl:"Formulier invullen →"},
    m_label:{fr:"En savoir plus", nl:"Meer weten"},
    m_text:{fr:"Nous vous recommandons d'utiliser votre <b>adresse professionnelle</b>&nbsp;; votre adresse personnelle est aussi acceptée. Si vous n'avez pas d'adresse professionnelle et que vous ne souhaitez pas utiliser votre adresse personnelle, un <b>module vous accompagne</b> pour créer la vôtre pour Famiformation.",
            nl:"Wij raden u aan uw <b>professioneel e-mailadres</b> te gebruiken&nbsp;; uw persoonlijk adres wordt ook aanvaard. Als u geen professioneel adres heeft en uw persoonlijk adres niet wilt gebruiken, helpt een <b>module</b> u om er een aan te maken voor Famiformation."},
    t1:{fr:"C'est quoi Famiformation ?", nl:"Wat is Famiformation?"},
    d1:{fr:"La réponse en 1 minute 🔎", nl:"Het antwoord in 1 minuut 🔎"},
    tag1:{fr:"Voir", nl:"Bekijken"},
    t3:{fr:"Pas d'adresse e-mail ?", nl:"Geen e-mailadres?"},
    d3:{fr:"Pas de panique, on vous guide pas à pas 🌱", nl:"Geen zorgen, wij helpen u stap voor stap 🌱"},
    tag3:{fr:"Suivre le guide", nl:"Volg de gids"},
    c_foot:{fr:"Réservé aux collaborateurs Famiflora<br>Besoin d'aide ? <b>admin@famiformation.com</b> ou le service RH",
              nl:"Enkel voor medewerkers van Famiflora<br>Hulp nodig ? <b>admin@famiformation.com</b> of de HR-dienst"},

    f_title:{fr:"Plus qu'une étape 🌱", nl:"Nog één stap 🌱"},
    f_sub:{fr:"Vos nom, prénom et adresse e-mail — et c'est fini.", nl:"Uw naam, voornaam en e-mailadres — en klaar."},
    l_nom:{fr:"Nom / Naam", nl:"Naam / Nom"},
    l_prenom:{fr:"Prénom / Voornaam", nl:"Voornaam / Prénom"},
    l_email:{fr:"Adresse e-mail", nl:"E-mailadres"},
    f_btn:{fr:"Je m'inscris 🍦", nl:"Ik schrijf mij in 🍦"},
    f_note:{fr:"Votre adresse sert uniquement à créer votre accès Famiformation. Elle n'est transmise à personne.",
             nl:"Uw adres dient enkel om uw Famiformation-toegang aan te maken. Het wordt aan niemand doorgegeven."},

    dn_title:{fr:"C'est enregistré, merci ! 🎉", nl:"Geregistreerd, bedankt! 🎉"},
    dn_sub:{fr:"Vous recevrez vos accès à Famiformation par e-mail.", nl:"U ontvangt uw toegang tot Famiformation per e-mail."},
    dn_hint:{fr:"🍦 <b>Votre ticket glace vous attend :</b> présentez cet écran au service RH pour le récupérer.",
              nl:"🍦 <b>Uw ijsjesticket wacht op u:</b> toon dit scherm aan de HR-dienst om het af te halen."}
  };

  /* Messages d'erreur du serveur, dans les deux langues. */
  const ERREURS = {
    nom_manquant:{fr:"Merci de remplir le nom et le prénom.", nl:"Gelieve naam en voornaam in te vullen."},
    email_invalide:{fr:"Adresse e-mail incorrecte — vérifiez la saisie.", nl:"Ongeldig e-mailadres — controleer de invoer."},
    trop_long:{fr:"Saisie trop longue, merci de raccourcir.", nl:"Invoer te lang, gelieve in te korten."},
    erreur_base:{fr:"Enregistrement impossible, réessayez dans un instant.", nl:"Registratie mislukt, probeer het zo dadelijk opnieuw."}
  };
  const DEJA = {fr:"Vous étiez déjà inscrit — rien à refaire 😉", nl:"U was al ingeschreven — niets te doen 😉"};

  const MIAM = {fr:["Hé hé 😏","Trop lent ! 🏃","Donnez votre e-mail, il est à vous 🍦"],
                 nl:["Hé hé 😏","Te traag! 🏃","Geef uw e-mail, hij is van u 🍦"]};

  let LANG='fr';
  function setLang(l){
    LANG=l;
    for(const id in T){ const el=document.getElementById(id); if(el) el.innerHTML=T[id][l]; }
    document.documentElement.lang=l;
    document.getElementById('btnFR').classList.toggle('active', l==='fr');
    document.getElementById('btnNL').classList.toggle('active', l==='nl');
    /* Le sous-titre de l'ecran de fin depend de l'etat renvoye par le serveur. */
    if(ETAT==='deja_inscrit'){ const e=document.getElementById('dn_sub'); if(e) e.innerHTML=DEJA[l]; }
    const err=document.getElementById('f_err');
    if(err && ERREURS[ETAT]) err.innerHTML=ERREURS[ETAT][l];
  }
  setLang('fr');
  function quit(){ window.location.href = CONFIG.quitUrl; }

  /* Compte a rebours — masque s'il n'y a pas de date ou si elle est passee. */
  const target = CONFIG.launchDate ? new Date(CONFIG.launchDate).getTime() : 0;
  function pad(x){return x<10?'0'+x:''+x;}
  function tick(){
    if(!target || target - Date.now() <= 0){
      const b=document.getElementById('cdbloc'); if(b) b.style.display='none';
      return;
    }
    let dm = target - Date.now();
    const d=Math.floor(dm/86400000), h=Math.floor(dm%86400000/3600000), m=Math.floor(dm%3600000/60000), s=Math.floor(dm%60000/1000);
    document.getElementById('cd_d').textContent=d;
    document.getElementById('cd_h').textContent=pad(h);
    document.getElementById('cd_m').textContent=pad(m);
    document.getElementById('cd_s').textContent=pad(s);
  }
  tick(); setInterval(tick,1000);

  function confetti(n){
    const colors=['#7cb342','#2e7d46','#ffcf33','#b6e07a','#fff'];
    for(let i=0;i<n;i++){
      const c=document.createElement('div'); c.className='cf';
      const s=6+Math.random()*8;
      c.style.width=s+'px'; c.style.height=(s*0.6)+'px';
      c.style.left=(Math.random()*100)+'vw';
      c.style.background=colors[Math.floor(Math.random()*colors.length)];
      c.style.animationDuration=(2.2+Math.random()*2)+'s';
      c.style.animationDelay=(Math.random()*0.6)+'s';
      document.body.appendChild(c);
      setTimeout(()=>c.remove(),5200);
    }
  }

  function enter(){
    document.getElementById('gate').style.display='none';
    const w=document.getElementById('welcome');
    w.style.display='flex';
    confetti(90);
    setTimeout(()=>{
      w.style.display='none';
      document.getElementById('content').style.display='block';
      window.scrollTo(0,0);
      document.getElementById('ticket').style.display='none'; /* le jeu vit sur l'ecran d'accueil uniquement */
    },2100);
  }

  let rewardShown=false;
  function bloom(){
    document.getElementById('st_sprout').classList.remove('on');
    document.getElementById('st_flower').classList.add('on');
    confetti(50);
  }
  /* Premier clic : la plante fleurit et la recompense apparait.
     C'est le bouton de la recompense qui ouvre ensuite le formulaire. */
  function goForm(e){
    bloom();
    if(!rewardShown){
      rewardShown=true;
      if(e) e.preventDefault();
      document.getElementById('reward').style.display='flex';
      return false;
    }
    openForm();
    return false;
  }
  function openForm(){
    document.getElementById('reward').style.display='none';
    document.getElementById('formscreen').style.display='flex';
    const n=document.getElementById('nom'); if(n) n.focus();
  }

  /* Ticket attrape-moi : deux fuites, puis le message. */
  let catches=0;
  function placeTicket(){
    if(catches>0) return;
    const pb=document.querySelector('.gate .plantbox');
    const t=document.getElementById('ticket');
    if(!pb||!t) return;
    const r=pb.getBoundingClientRect();
    t.style.left=Math.min(r.right+6, window.innerWidth-120)+'px';
    t.style.top=Math.max(8, r.top-14)+'px';
    t.style.right='auto'; t.style.bottom='auto';
  }
  window.addEventListener('resize', placeTicket);
  setTimeout(placeTicket, 150);
  function bubble(txt,x,y,long){
    const b=document.createElement('div'); b.className='tbubble'; b.innerHTML=txt;
    b.style.left=Math.max(8,Math.min(x, window.innerWidth-240))+'px';
    b.style.top=Math.max(10,y-52)+'px';
    document.body.appendChild(b); setTimeout(()=>b.remove(), long?3200:1100);
  }
  function catchTicket(e){
    const t=document.getElementById('ticket');
    const r=t.getBoundingClientRect();
    if(catches<2){
      bubble(MIAM[LANG][catches], r.left, r.top, false);
      const vw=window.innerWidth, vh=window.innerHeight;
      const nx=10+Math.random()*(vw-110);
      const ny=110+Math.random()*(vh-260);
      t.style.left=nx+'px'; t.style.top=ny+'px';
      t.style.right='auto'; t.style.bottom='auto';
      catches++;
    } else {
      bubble(MIAM[LANG][2], r.left, r.top, true);
      confetti(25);
    }
  }

  /* Reprise apres un envoi du formulaire : on saute le parcours et on montre
     directement le resultat, sinon la personne refait tout le chemin. */
  if(SUCCES){
    document.getElementById('gate').style.display='none';
    document.getElementById('ticket').style.display='none';
    document.getElementById('done').style.display='flex';
    confetti(80);
  } else if(ETAT){
    /* Erreur de saisie : on rouvre le formulaire avec le message. */
    document.getElementById('gate').style.display='none';
    document.getElementById('ticket').style.display='none';
    document.getElementById('content').style.display='block';
    const err=document.getElementById('f_err');
    if(err && ERREURS[ETAT]){ err.style.display='block'; err.innerHTML=ERREURS[ETAT][LANG]; }
    rewardShown=true;
    openForm();
  }
</script>
</body></html>
