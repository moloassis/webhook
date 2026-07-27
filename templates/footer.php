    </main>
    <?php $jsVer = file_exists(__DIR__ . '/../assets/js/index.js') ? filemtime(__DIR__ . '/../assets/js/index.js') : time(); ?>
    <!-- Script principal global -->
    <script src="assets/js/index.js?v=<?= $jsVer ?>"></script>
</body>
</html>
