<?php
// ============================================================
// LA CONFIRMATION AVANT D'AFFECTER — partagee par les deux vues.
//
// Le traitement ne bloque pas une affectation douteuse (personne deja prise ce
// jour-la, disponibilite non renseignee…) : il la met EN ATTENTE, dans
// $pendingConfirm, et laisse la vue poser la question.
//
// ⚠️ CE FICHIER EXISTE A CAUSE D'UN BUG. La question n'etait posee que dans la
// vue detaillee. Depuis le classeur, une affectation qui declenchait le moindre
// avertissement ne produisait RIEN : pas d'affectation, pas de message, pas de
// fenetre. On cliquait, on ne comprenait pas, on recommencait. C'est ce qui
// donnait « dans l'excel ca ne marche pas ».
//
// Les deux vues montrent la meme chose, seule la mise en forme change : la
// question doit donc etre posee des deux cotes, et par le meme code. Un second
// exemplaire recopie aurait fini par diverger de celui-ci.
//
// ⚠️ TOUT CE QUI A ETE SAISI REPART DANS LE FORMULAIRE — y compris le mot de
// l'agence. Faire retaper quelqu'un parce qu'on lui a pose une question est le
// meilleur moyen qu'il ne le retape pas.
// ============================================================

if ($pendingConfirm !== null):
    $confirmMode = (string) ($pendingConfirm['matching_mode'] ?? 'name');
?>
<div class="fjc-voile" id="fjcConfirm">
    <div class="fjc-boite" role="dialog" aria-modal="true">
        <div class="fjc-ic">⚠️</div>
        <h3><?php echo e(fjhT('Confirmation', 'Bevestiging')); ?></h3>
        <p class="fjc-texte"><?php echo e($pendingConfirm['message']); ?></p>
        <div class="fjc-actions">
            <button type="button" class="fjc-btn fjc-non"
                    onclick="document.getElementById('fjcConfirm').style.display='none';">
                <?php echo e(fjhT('Non', 'Nee')); ?>
            </button>
            <form method="POST" style="display:inline; flex:1;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="assign_student" value="1">
                <input type="hidden" name="request_id" value="<?php echo (int) $pendingConfirm['request_id']; ?>">
                <input type="hidden" name="matching_mode" value="<?php echo e($confirmMode); ?>">
                <?php if ($confirmMode === 'list'): ?>
                    <input type="hidden" name="student_id" value="<?php echo (int) ($pendingConfirm['student_id'] ?? 0); ?>">
                <?php else: ?>
                    <input type="hidden" name="student_name" value="<?php echo e($pendingConfirm['student_name']); ?>">
                <?php endif; ?>
                <?php if (trim((string) ($pendingConfirm['agency_comment'] ?? '')) !== ''): ?>
                    <input type="hidden" name="agency_comment" value="<?php echo e($pendingConfirm['agency_comment']); ?>">
                <?php endif; ?>
                <input type="hidden" name="confirm_assign" value="1">
                <button type="submit" class="fjc-btn fjc-oui" style="width:100%;">
                    <?php echo e(fjhT('Oui, affecter', 'Ja, toewijzen')); ?>
                </button>
            </form>
        </div>
    </div>
</div>
<style>
    /* Styles portes par le partial lui-meme : il s'invite dans deux pages qui
       n'ont pas la meme feuille, et une fenetre sans mise en forme est une
       fenetre qu'on ne voit pas. Prefixe fjc- pour ne rien bousculer. */
    .fjc-voile { position: fixed; inset: 0; background: rgba(15,36,29,.5); display: flex;
        align-items: center; justify-content: center; z-index: 9500; padding: 16px; }
    .fjc-boite { background: #fff; border-radius: 20px; padding: 26px 24px 20px; max-width: 440px;
        width: 100%; box-shadow: 0 30px 70px rgba(8,22,17,.4); text-align: center; font-family: inherit; }
    .fjc-ic { width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 50%; background: #fdecea;
        color: #c0392b; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
    .fjc-boite h3 { margin: 0 0 8px; color: #21362a; font-size: 1.2rem; }
    .fjc-texte { margin: 0 0 18px; color: #4a5a51; font-size: .92rem; line-height: 1.5; }
    .fjc-actions { display: flex; gap: 10px; }
    .fjc-btn { border: none; border-radius: 12px; padding: 13px 14px; font-weight: 800; font-size: .92rem;
        cursor: pointer; font-family: inherit; }
    .fjc-non { flex: 1; background: #eef2f0; color: #3a4a42; }
    .fjc-non:hover { background: #e2e8e5; }
    .fjc-oui { background: #2d5a37; color: #fff; }
    .fjc-oui:hover { background: #24492c; }
</style>
<?php endif; ?>
