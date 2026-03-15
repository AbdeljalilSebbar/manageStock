<?php
if (extension_loaded('gd')) {
    echo "✅ GD Library is ENABLED!";
    
    // N-choufo wach kat-da3m WebP b-dabt
    $info = gd_info();
    if ($info['WebP Support']) {
        echo "<br>🚀 WebP Support is ACTIVE!";
    } else {
        echo "<br>⚠️ WebP Support is MISSING.";
    }
} else {
    echo "❌ GD Library is NOT found.";
}
?>