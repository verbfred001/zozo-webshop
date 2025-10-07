<footer class="modern-footer">
  <div class="footer-columns">
    <div class="footer-col">
      <h3>ZOZO</h3>
      <p style="font-weight:bold; margin-bottom:0.5em;"><?= t('footer_get_in_touch') ?></p>
      <p>Drie Beloftenstraat 30<br>
        Zonnebeke<br>
        BE 0891 305 383
      </p>
      <p>
        <a href="tel:0479129656" style="font-weight:bold;">0479129656</a><br>
      </p>
    </div>
    <div class="footer-col">
      <h3><?= t('footer_opening_hours') ?></h3>
      <table class="footer-table">
        <tr>
          <td><?= t('footer_mo_fr') ?></td>
          <td><?= t('footer_closed') ?></td>
        </tr>
        <tr>
          <td><?= t('footer_sa_su') ?></td>
          <td><?= t('footer_closed') ?></td>
        </tr>
      </table>
    </div>
    <div class="footer-col">
      <h3><?= t('footer_easy_order') ?></h3>
      <p><?= t('footer_easy_order_text') ?></p>
      </p>
    </div>
  </div>
  <div class="footer-bottom">
    <?= str_replace('%YEAR%', date("Y"), t('footer_copyright')) ?>
  </div>
</footer>