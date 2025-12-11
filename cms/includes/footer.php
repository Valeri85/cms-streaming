<?php
/**
 * CMS Footer Component
 * 
 * Closes main, layout div, body, and html tags.
 * Optionally includes JavaScript files.
 * 
 * Usage:
 *   $extraJs = ['js/custom.js'];  // Optional: Additional JS files
 *   include __DIR__ . '/includes/footer.php';
 */

$extraJs = $extraJs ?? [];
?>
        </main>
    </div>
    
    <?php foreach ($extraJs as $jsFile): ?>
        <script src="<?php echo htmlspecialchars($jsFile); ?>"></script>
    <?php endforeach; ?>
</body>
</html>