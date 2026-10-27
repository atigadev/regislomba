            <!-- end: page content -->
        </section>
    </div>
</section>

<script src="<?= url('assets/porto/vendor/jquery-browser-mobile/jquery.browser.mobile.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/common/common.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/nanoscroller/nanoscroller.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/magnific-popup/jquery.magnific-popup.min.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/jquery-placeholder/jquery.placeholder.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/jquery-appear/jquery.appear.js') ?>"></script>
<script src="<?= url('assets/porto/js/theme.js') ?>"></script>
<script src="<?= url('assets/porto/js/theme.init.js') ?>"></script>
<script>
// Ciut/terbentangnya sidebar diingat antar halaman.
(function () {
    var tombol = document.querySelector('.sidebar-toggle');
    if (!tombol) { return; }
    tombol.addEventListener('click', function () {
        setTimeout(function () {
            try {
                localStorage.setItem('porto-sidebar-ciut',
                    document.documentElement.classList.contains('sidebar-left-collapsed') ? '1' : '0');
            } catch (e) { /* mode privat */ }
        }, 0);
    });
})();
</script>
<?php if (!empty($skripHalaman)) { echo $skripHalaman; } ?>
</body>
</html>
