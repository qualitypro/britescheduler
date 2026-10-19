    </main>
</div>

<script src="<?=htmlspecialchars(app_url('/assets/js/brite-app-shell.js'))?>"></script>
<?php if ($tenant['role'] === 'owner'): ?>
<script src="<?=htmlspecialchars(
    app_url('/assets/js/brite-activity-hub.js'),
    ENT_QUOTES,
    'UTF-8'
)?>"></script>
<?php endif; ?>
</body>
</html>
