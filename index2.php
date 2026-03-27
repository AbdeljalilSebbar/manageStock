<?php
$host = "193.203.168.42";
$dbname = "u899739665_s_inbat_work";
$user = "u899739665_s_inbat_work";
$pass = "U3&bwEytJ";

try {
	$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
	]);
} catch (PDOException $e) {
	die("Erreur de connexion");
}

if (!file_exists('uploads')) {
	mkdir('uploads', 0777, true);
}

if (isset($_GET['fetch'])) {
	header('Content-Type: application/json; charset=utf-8');
	$stmt = $pdo->query("SELECT * FROM products WHERE status = '1' ORDER BY id DESC");
	$products = $stmt->fetchAll();
	foreach ($products as &$p) {
		if ($p['image'] && !file_exists($p['image'])) {
			$p['image'] = null;
		}
	}
	echo json_encode($products, JSON_UNESCAPED_UNICODE);
	exit;
}

if (isset($_GET['fetch_categories'])) {
	header('Content-Type: application/json');
	$stmt = $pdo->query("SELECT name FROM categories ORDER BY name ASC");
	echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
	exit;
}

if (isset($_GET['fetch_logs'])) {
	header('Content-Type: application/json; charset=utf-8');
	$p_id = intval($_GET['fetch_logs']);
	$stmt = $pdo->prepare("SELECT action_type, details, notes, action_date FROM product_logs WHERE product_id = ? ORDER BY action_date DESC");
	$stmt->execute([$p_id]);
	echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


	function addLog($pdo, $p_id, $name, $cat, $buyDate, $qty, $unit, $type, $details, $notes)
	{
		$sql = "INSERT INTO product_logs 
            (product_id, product_name, catégorie, buyDate, qty, unit, action_type, details, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([$p_id, $name, $cat, $buyDate, $qty, $unit, $type, $details, $notes]);
	}



	$action = $_POST['action'] ?? '';
	// hadi bch add new product
	if ($action === 'save') {
		$id = $_POST['id'] ?? null;
		$name = $_POST['name'] ?? '';
		$cat = $_POST['cat'] ?? '';
		$buyDate = $_POST['buyDate'] ?? '';
		$input_qty = (isset($_POST['qty']) && $_POST['qty'] !== '') ? floatval($_POST['qty']) : 0;
		$unit = $_POST['unit'] ?? '';
		$input_unit = $_POST['input_unit'] ?? $unit;
		$exp = $_POST['exp'] ?? '';
		$notes = $_POST['notes'] ?? '';
		$imagePath = $_POST['existing_image'] ?? null;


		// داخل if ($action === 'save')
		// التحقق من الحقول المطلوبة (Validation)
		if (empty($name)) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'المرجو إدخال اسم المنتج']);
			exit;
		}

		if (empty($cat)) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'المرجو اختيار أو كتابة صنف (Catégorie)']);
			exit;
		}

		if (empty($unit)) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'المرجو تحديد الوحدة (Unité)']);
			exit;
		}

		if (empty($imagePath) && !isset($_POST['existing_image'])) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'المرجو تحميل صورة للمنتج']);
			exit;
		}
		$final_qty = $input_qty;
		if ($input_unit !== $unit) {
			$u1 = strtolower(trim($input_unit));
			$u2 = strtolower(trim($unit));
			if ($u1 == 'kg' && ($u2 == 'tonnes' || $u2 == 't'))
				$final_qty = $input_qty / 1000;
			elseif ($u1 == 'g' && $u2 == 'kg')
				$final_qty = $input_qty / 1000;
			elseif ($u1 == 'g' && ($u2 == 'tonnes' || $u2 == 't'))
				$final_qty = $input_qty / 1000000;
			elseif ($u1 == 'ml' && $u2 == 'l')
				$final_qty = $input_qty / 1000;
			elseif (($u1 == 'tonnes' || $u1 == 't') && $u2 == 'kg')
				$final_qty = $input_qty * 1000;
			echo "hada ana hna" + $final_qty + "hada ana hna" + $input_qty + "hada ana hna" + $u1 + "hada ana hna" + $u2;
		}
		if (!empty($cat)) {
			$stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
			$stmt->execute([$cat]);
		}
		// TRAITEMENT IMAGE
		if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
			$tmpPath = $_FILES['image']['tmp_name'];
			$extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
			$newName = uniqid() . '.webp';
			$target = 'uploads/' . $newName;

			// Hna l-partie l-mohimma: ila fchel l-traitement WebP, n-dirou ghir move_uploaded_file
			$imgSuccess = false;
			$info = @getimagesize($tmpPath);

			if ($info && function_exists('imagecreatefromjpeg')) {
				try {
					$img = null;
					switch ($info[2]) {
						case IMAGETYPE_JPEG:
							$img = @imagecreatefromjpeg($tmpPath);
							break;
						case IMAGETYPE_PNG:
							$img = @imagecreatefrompng($tmpPath);
							break;
						case IMAGETYPE_WEBP:
							$img = @imagecreatefromwebp($tmpPath);
							break;
					}

					if ($img && function_exists('imagewebp')) {
						if ($info[2] === IMAGETYPE_PNG) {
							imagepalettetotruecolor($img);
							imagealphablending($img, true);
							imagesavealpha($img, true);
						}
						if (imagewebp($img, $target, 80)) {
							$imagePath = $target;
							$imgSuccess = true;
						}
						imagedestroy($img);
					}
				} catch (Exception $e) {
					$imgSuccess = false;
				}
			}

			// Fallback: Ila l-GD library fchlat (bach l-produit mat-bloquach)
			if (!$imgSuccess) {
				$rawTarget = 'uploads/' . uniqid() . '.' . $extension;
				if (move_uploaded_file($tmpPath, $rawTarget)) {
					$imagePath = $rawTarget;
				}
			}

			// Debug: had l-echo ghadi t-choufha f l-Network Tab dial l-browser
			// echo "DEBUG: Image saved at -> " . $imagePath; 
		}


		$lastAction = !empty($id) ? "Modifié le " . date('d/m/Y') : "Ajouté le " . date('d/m/Y');

		if (!empty($name)) {
			if (!empty($id)) {
				$sql = "UPDATE products SET name=?, catégorie=?, buyDate=?, qty=?, unit=?, exp=?, notes=?, lastAction=?, image=? WHERE id=?";
				$params = [$name, $cat, $buyDate, $final_qty, $unit, $exp, $notes, $lastAction, $imagePath, $id];
			} else {
				$sql = "INSERT INTO products (name, catégorie, buyDate, first_qty, qty, unit, exp, notes, lastAction, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '1')";
				$params = [$name, $cat, $buyDate, $final_qty, $final_qty, $unit, $exp, $notes, $lastAction, $imagePath];
			}

			$pdo->prepare($sql)->execute($params);
			$currentId = $id ?: $pdo->lastInsertId();
			$logType = !empty($id) ? "Modification" : "Ajout";
			$logDetail = ($input_unit !== $unit) ? "Entrée: $input_qty $input_unit (Converti en $final_qty $unit)" : "Entrée de stock: $final_qty $unit";

			addLog($pdo, $currentId, $name, $cat, $buyDate, $final_qty, $unit, $logType, $logDetail, $notes);
		}
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'path' => $imagePath]);
		exit;
	}
	// hna nst3ml chi product
	// if ($action === 'use') {
	// 	$id = intval($_POST['id']);
	// 	$input_qty = floatval($_POST['used_qty']);
	// 	$input_unit = $_POST['used_unit'] ?? '';
	// 	$formatted_date = date('d/m/Y à H:i', strtotime($_POST['use_date']));

	// 	$s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
	// 	$s->execute([$id]);
	// 	$p = $s->fetch();

	// 	if ($p) {
	// 		$base_unit = strtolower(trim($p['unit']));
	// 		$used_unit = strtolower(trim($input_unit));
	// 		$converted_qty = $input_qty;

	// 		if ($used_unit !== $base_unit) {
	// 			// All checks must be in lowercase here
	// 			if ($used_unit == 'kg' && ($base_unit == 'tonnes' || $base_unit == 't')) {
	// 				$converted_qty = $input_qty / 1000;
	// 			} elseif ($used_unit == 'g' && $base_unit == 'kg') {
	// 				$converted_qty = $input_qty / 1000;
	// 			} elseif ($used_unit == 'g' && ($base_unit == 'tonnes' || $base_unit == 't')) {
	// 				$converted_qty = $input_qty / 1000000;
	// 			} elseif ($used_unit == 'ml' && $base_unit == 'l') {
	// 				$converted_qty = $input_qty / 1000;
	// 			} elseif (($used_unit == 'tonnes' || $used_unit == 't') && $base_unit == 'kg') {
	// 				$converted_qty = $input_qty * 1000;
	// 			}
	// 		}

	// 		$lastAction = "⚡ Utilisé ($input_qty $input_unit) le $formatted_date";

	// 		// Execute the subtraction
	// 		$stmt = $pdo->prepare("UPDATE products SET qty = qty - ?, lastAction = ? WHERE id = ?");
	// 		$stmt->execute([$converted_qty, $lastAction, $id]);

	// 		addLog(
	// 			$pdo,
	// 			$id,
	// 			$p['name'],
	// 			$p['catégorie'],
	// 			$p['buyDate'],
	// 			$converted_qty,
	// 			$p['unit'],
	// 			'Utilisation',
	// 			"Consommé $input_qty $input_unit (soit $converted_qty {$p['unit']})",
	// 			$p['notes']
	// 		);
	// 	}
	// }
	if ($action === 'use') {
		$id = intval($_POST['id']);
		$input_qty = floatval($_POST['used_qty']);
		$input_unit = $_POST['used_unit'] ?? '';
		$formatted_date = date('d/m/Y à H:i', strtotime($_POST['use_date']));

		if (!empty($input_qty)) {
			if ($input_qty < 0) {
				// 1. كنقولو للمتصفح أننا غنصيفطو JSON
				header('Content-Type: application/json');

				// 2. كنصيفطو الرد فيه success false والرسالة ديال الخطأ
				echo json_encode([
					'success' => false,
					'message' => 'الكمية غير كافية'
				]);

				// 3. كنحبسو السكريبت باش ما يكملش الأوامر الأخرى
				exit;
			}
		}
		$s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
		$s->execute([$id]);
		$p = $s->fetch();

		if ($p) {
			$base_unit = strtolower(trim($p['unit']));
			$used_unit = strtolower(trim($input_unit));
			$converted_qty = $input_qty;

			// تحويل الوحدات (نفس الكود اللي كان عندك)
			if ($used_unit !== $base_unit) {
				if ($used_unit == 'kg' && ($base_unit == 'tonnes' || $base_unit == 't')) $converted_qty = $input_qty / 1000;
				elseif ($used_unit == 'g' && $base_unit == 'kg') $converted_qty = $input_qty / 1000;
				elseif ($used_unit == 'g' && ($base_unit == 'tonnes' || $base_unit == 't')) $converted_qty = $input_qty / 1000000;
				elseif ($used_unit == 'ml' && $base_unit == 'l') $converted_qty = $input_qty / 1000;
				elseif (($used_unit == 'tonnes' || $used_unit == 't') && $base_unit == 'kg') $converted_qty = $input_qty * 1000;
			}

			// --- الزيادة المهمة هنا: التحقق من الكمية ---
			if ($converted_qty > $p['qty']) {
				header('Content-Type: application/json');
				echo json_encode(['success' => false, 'error' => 'الكمية غير كافية! المتوفر حاليا هو: ' . $p['qty'] . ' ' . $p['unit']]);
				exit;
			}

			$lastAction = "⚡ Utilisé ($input_qty $input_unit) le $formatted_date";
			$stmt = $pdo->prepare("UPDATE products SET qty = qty - ?, lastAction = ? WHERE id = ?");
			$stmt->execute([$converted_qty, $lastAction, $id]);

			addLog($pdo, $id, $p['name'], $p['catégorie'], $p['buyDate'], $converted_qty, $p['unit'], 'Utilisation', "Consommé $input_qty $input_unit", $p['notes']);

			header('Content-Type: application/json');
			echo json_encode(['success' => true]);
			exit;
		}
	}
	// hna bch nmsah chi data
	if ($action === 'delete') {
		$id = intval($_POST['id']);

		// 1. Tjib l-data d l-produit gbel ma i-t-msah
		$s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
		$s->execute([$id]);
		$p = $s->fetch();

		if ($p) {
			// 2. Update status bach may-ban-ch f l-app
			$pdo->prepare("UPDATE products SET status = '0' WHERE id = ?")->execute([$id]);

			// 3. Sejel f l-logs bli had l-produit t-msah
			addLog(
				$pdo,
				$id,
				$p['name'],
				$p['catégorie'],
				$p['buyDate'],
				$p['qty'],
				$p['unit'],
				'Suppression',
				"Produit archivé (status: delete)",
				$p['notes']
			);
		}
	}
	header('Content-Type: application/json');
	echo json_encode(['success' => true]);
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AgriStock Pro</title>
	<style>
		:root {
			--primary: #2d6a4f;
			--secondary: #52b788;
			--bg: #f4f7f6;
			--text: #1e293b;
			--danger: #ef4444;
			--gray: #64748b;
			--success: #16a34a;
			--use-color: #0ea5e9;
		}

		body {
			font-family: 'Segoe UI', sans-serif;
			background: var(--bg);
			color: var(--text);
			margin: 0;
			display: flex;
			height: 100vh;
			overflow: hidden;
		}

		nav {
			width: 80px;
			background: var(--primary);
			display: flex;
			flex-direction: column;
			align-items: center;
			padding: 20px 0;
			color: white;
		}

		main {
			flex: 1;
			padding: 30px;
			overflow-y: auto;
		}

		header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
		}

		/* --- STYLE DYAL LES DIV TOTALS --- */
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
			margin-bottom: 25px;
		}

		.stat-card {
			background: white;
			padding: 20px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
			border-left: 5px solid var(--primary);
		}

		.stat-card h3 {
			margin: 0;
			font-size: 0.75rem;
			color: var(--gray);
			text-transform: uppercase;
			letter-spacing: 1px;
		}

		.stat-card .value {
			font-size: 1.6rem;
			font-weight: bold;
			margin-top: 5px;
			color: var(--primary);
		}

		.stat-card .unit {
			font-size: 0.9rem;
			color: var(--gray);
			margin-left: 5px;
		}

		.table-container {
			background: white;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
			overflow-x: auto;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			min-width: 900px;
		}

		th {
			background: #f8fafc;
			padding: 15px;
			text-align: left;
			color: var(--gray);
			font-size: 0.85rem;
			border-bottom: 2px solid #edf2f7;
		}

		td {
			padding: 15px;
			border-bottom: 1px solid #f1f5f9;
			font-size: 0.9rem;
		}

		.btn {
			padding: 8px 14px;
			border-radius: 8px;
			border: none;
			cursor: pointer;
			font-weight: 600;
			font-size: 0.85rem;
		}

		.search-input {
			padding: 10px 15px;
			border: 1px solid #ddd;
			border-radius: 8px;
			width: 300px;
			margin-bottom: 15px;
			font-size: 0.9rem;
			outline: none;
			transition: 0.2s;
		}

		.search-input:focus {
			border-color: var(--primary);
			box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.2);
		}

		.stock-info-box {
			background: #f8fafc;
			border: 1px dashed #cbd5e1;
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 20px;
			text-align: center;
		}

		.stock-info-box span {
			font-size: 1.5rem;
			font-weight: bold;
			color: var(--primary);
			display: block;
			margin-top: 5px;
		}

		.btn-primary {
			background: var(--primary);
			color: white;
		}

		.btn-outline {
			background: white;
			color: var(--primary);
			border: 1px solid var(--primary);
		}

		.btn-danger {
			background: #fee2e2;
			color: var(--danger);
		}

		.btn-use {
			background: #e0f2fe;
			color: var(--use-color);
		}

		.drawer {
			position: fixed;
			top: 0;
			right: -500px;
			width: 400px;
			height: 100vh;
			background: white;
			box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
			transition: 0.4s;
			padding: 30px;
			z-index: 100;
			overflow-y: auto;
		}

		.drawer.open {
			right: 0;
		}

		.prod-img {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			object-fit: cover;
			background: #eee;
		}

		.overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			display: none;
			z-index: 90;
		}

		.form-group {
			margin-bottom: 15px;
		}

		input[type="text"]:not(.search-input),
		input[type="number"],
		input[type="date"],
		input[type="datetime-local"],
		select,
		textarea {
			width: 100%;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-family: inherit;
		}

		input:focus,
		select:focus,
		textarea:focus {
			border-color: var(--primary);
			outline: none;
		}

		.page-section {
			display: none;
		}

		.active {
			display: block;
		}

		.badge {
			padding: 4px 10px;
			border-radius: 6px;
			font-size: 0.75rem;
			font-weight: bold;
		}

		.bg-ok {
			background: #dcfce7;
			color: var(--success);
		}

		.bg-low {
			background: #ffedd5;
			color: var(--warning);
		}

		tr.clickable:hover {
			background: #f0fdf4;
			cursor: pointer;
		}

		.details-img {
			width: 100%;
			height: 200px;
			object-fit: cover;
			border-radius: 12px;
			margin-bottom: 20px;
			border: 1px solid #ddd;
		}

		.info-item {
			margin-bottom: 15px;
			border-bottom: 1px solid #eee;
			padding-bottom: 8px;
		}

		.info-label {
			color: var(--gray);
			font-size: 0.75rem;
			text-transform: uppercase;
			display: block;
		}

		.info-value {
			font-weight: bold;
			font-size: 1.1rem;
			color: var(--primary);
		}

		.notes-box {
			background: #f9f9f9;
			padding: 10px;
			border-radius: 8px;
			border-left: 4px solid var(--primary);
			margin-top: 10px;
		}

		.timeline {
			margin-top: 15px;
			border-left: 2px solid #e2e8f0;
			padding-left: 15px;
		}

		.log-item {
			margin-bottom: 12px;
			position: relative;
			font-size: 0.85rem;
		}

		.log-item::before {
			content: '';
			position: absolute;
			left: -21px;
			top: 5px;
			width: 10px;
			height: 10px;
			background: var(--primary);
			border-radius: 50%;
		}

		.log-date {
			color: var(--gray);
			font-size: 0.75rem;
			display: block;
		}

		.bg-empty {
			background: #fee2e2 !important;
			color: #b91c1c !important;
			border: 1px solid #fecaca;
		}

		.btn-disabled {
			background: #f1f5f9 !important;
			color: #94a3b8 !important;
			cursor: not-allowed !important;
			opacity: 0.6;
		}
	</style>
</head>

<body>
	<div class="overlay" id="overlay" onclick="closeDrawer()"></div>

	<div class="drawer" id="drawer">
		<h2 id="drawerTitle">Produit</h2>
		<form id="saveForm">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" id="form_id">
			<input type="hidden" name="existing_image" id="form_existing_image">
			<div class="form-group"><label>Image</label><input type="file" name="image" accept="image/*"></div>
			<div class="form-group">
				<label>Nom</label>
				<input type="text" name="name" id="form_name" required>
			</div>
			<div class="form-group">
				<label>Catégorie</label>
				<input type="text" name="cat" id="form_cat" list="category_options" placeholder="Choisir ou écrire..." required>

				<datalist id="category_options">
					<option value="Alaf / Aliments bétail">
					<option value="Dwa / Médicaments vétérinaires">
					<option value="Engrais / Fertilizers">
					<option value="Semences">
				</datalist>
			</div>
			<div style="display: flex; gap: 10px;">
				<div class="form-group">
					<label>Quantité</label>
					<input type="number" name="qty" id="form_qty" step="0.01" required>
				</div>
				<div class="form-group" style="flex:1;">
					<label>Unité</label>
					<input type="text" name="unit" id="form_unit" list="unit_options" placeholder="Unité..." required>

					<datalist id="unit_options">
						<option value="KG">
						<option value="G">
						<option value="Tonnes">
						<option value="L">
						<option value="ml">
						<option value="Sacs">
						<option value="pieces">
					</datalist>
				</div>
			</div>
			<div class="form-group">
				<label>Achat</label>
				<input type="date" name="buyDate" id="form_buy" required>
			</div>
			<div class="form-group">
				<label>Exp</label>
				<input type="date" name="exp" id="form_exp">
			</div>
			<div class="form-group">
				<label>Notes</label>
				<textarea name="notes" id="form_notes"></textarea>
			</div>
			<button type="submit" class="btn btn-primary" style="width: 100%;">💾 Enregistrer</button>
		</form>
	</div>
	<div class="drawer" id="useDrawer">
		<h2>⚡ Utiliser</h2>
		<div class="stock-info-box">
			Stock Disponible:
			<span id="currentStockDisplay">0 KG</span>
		</div>
		<form id="useForm">
			<input type="hidden" name="action" value="use">
			<input type="hidden" name="id" id="use_form_id">
			<div class="form-group">
				<label>Quantité à retirer du stock</label>
				<div style="display: flex; gap: 10px; align-items: center;">
					<input type="number" name="used_qty" id="use_form_qty" step="0.01" min="0.01" required style="flex: 2;">

					<div style="flex: 1;">
						<input type="text" name="used_unit" id="use_form_unit" list="units_list" placeholder="Unité" required
							style="width: 100%; font-weight: bold; border: 1px solid #ccc; padding: 8px; border-radius: 4px; color: var(--gray);">
						<datalist id="units_list">
							<option value="KG">
							<option value="G">
							<option value="Tonnes">
							<option value="L">
							<option value="ml">
							<option value="Sacs">
							<option value="pieces">
						</datalist>
					</div>
				</div>
			</div>
			<div class="form-group">
				<label>Date</label>
				<input type="datetime-local" name="use_date" id="use_form_date" required>
			</div>
			<button type="submit" class="btn btn-use" style="width: 100%; color: white;">✓ Valider</button>
		</form>
	</div>
	<nav>
		<div style="font-size: 32px; margin-top: 20px;">🌾</div>
	</nav>
	<main>
		<header>
			<h1>AgriStock Pro</h1>
			<div>
				<button class="btn btn-outline" onclick="switchPage('dashboard')">📊 Dashboard</button>
				<button class="btn btn-primary" onclick="switchPage('manage')">⚙️ Gestion</button>
			</div>
		</header>

		<section id="dashboard" class="page-section active">

			<div id="statsSummary" class="stats-grid"></div>
			<input type="text" class="search-input" placeholder="🔍 Rechercher dans le tableau de bord..." onkeyup="searchTable(this, 'dashTable')">

			<div class="table-container">
				<table id="dashTable">
					<thead>
						<tr>
							<th>Produit</th>
							<th>Catégorie</th>
							<th>Stock</th>
							<th>Achat</th>
							<th>Exp</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</section>

		<section id="manage" class="page-section">
			<input type="text" class="search-input" style="margin-bottom: 0;" placeholder="🔍 Rechercher un produit..." onkeyup="searchTable(this, 'manTable')">

			<button class="btn btn-primary" onclick="openAdd()" style="margin-bottom: 20px;">+ Nouveau</button>
			<div class="table-container">
				<table id="manTable">
					<thead>
						<tr>
							<th>Nom</th>
							<th>Catégorie</th>
							<th>Stock</th>
							<th>Achat</th>
							<th>Exp</th>
							<th>Dernière Action</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</section>
	</main>
	<div class="drawer" id="detailsDrawer" style="display: flex; flex-direction: column;">
		<h2>Détails du Produit</h2>


		<div id="detailsContent" style="flex: 1; overflow-y: auto; padding-right: 10px; max-height: 77%;">
		</div>

		<button class="btn btn-outline" style="width: 100%; margin-top: 20px; flex-shrink: 0;" onclick="closeDrawer()">
			Fermer
		</button>
	</div>
	<script>
		const API_URL = window.location.pathname;
		const PLACEHOLDER = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2250%22%20height%3D%2250%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23eee%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20text-anchor%3D%22middle%22%20fill%3D%22%23aaa%22%3ENo%20Img%3C%2Ftext%3E%3C%2Fsvg%3E';

		// Real-time search function
		function searchTable(input, tableId) {
			let filter = input.value.toUpperCase();
			let table = document.getElementById(tableId);
			let tr = table.getElementsByTagName("tr");

			for (let i = 1; i < tr.length; i++) {
				let row = tr[i];
				// textContent kiy-khlli l-baht i-koun 3la s-stta kamla (smiya, catégorie, etc.)
				let textValue = row.textContent || row.innerText;
				if (textValue.toUpperCase().indexOf(filter) > -1) {
					tr[i].style.display = "";
				} else {
					tr[i].style.display = "none";
				}
			}
		}
		// async function loadProducts() {
		// 	const response = await fetch('index2.php?fetch=1');
		// 	const products = await response.json();

		// 	const dashBody = document.querySelector('#dashTable tbody');
		// 	const manBody = document.querySelector('#manTable tbody');
		// 	const statsSummary = document.getElementById('statsSummary');

		// 	dashBody.innerHTML = '';
		// 	manBody.innerHTML = '';

		// 	// 1. Structure jdida: { "Catégorie": { "Unité1": sum, "Unité2": sum } }
		// 	const totals = {};

		// 	products.forEach(p => {
		// 		const cat = p.catégorie || "Non classé";
		// 		const unit = p.unit || "u";
		// 		const qty = parseFloat(p.qty) || 0;
		// 		const img = p.image ? p.image : PLACEHOLDER;


		// 		if (!totals[cat]) {
		// 			totals[cat] = {};
		// 		}

		// 		if (!totals[cat][unit]) {
		// 			totals[cat][unit] = 0;
		// 		}

		// 		totals[cat][unit] += qty;

		// 		// 2. Render Tables (kima kano)
		// 		let badgeClass = 'bg-ok';
		// 		let useBtnAttr = `class="btn btn-use" onclick='openUse(${JSON.stringify(p).replace(/'/g, "&apos;")})'`;
		// 		// إذا كانت الكمية 0 أو أقل
		// 		if (qty <= 0) {
		// 			badgeClass = 'bg-empty'; // أحمر
		// 			useBtnAttr = `class="btn btn-disabled" disabled`; // تعطيل الزر
		// 		} 
		// 		// إذا كانت الكمية قليلة (أقل من 5 مثلا)
		// 		else if (qty <= 5) {
		// 			badgeClass = 'bg-low'; // برتقالي
		// 		}
		// 		const rowData = JSON.stringify(p).replace(/'/g, "&apos;");

		// 		dashBody.innerHTML += `<tr class="clickable" onclick='showDetails(${rowData})'><td><div style="display:flex;align-items:center;gap:10px;"><img src="${img}" class="prod-img" onerror="this.src='${PLACEHOLDER}'"><b>${p.name}</b></div></td><td>${cat}</td><td><span class="badge ${badgeClass}">${p.qty} ${unit}</span></td><td>${p.buyDate}</td><td>${p.exp}</td><td><small>${p.lastAction}</small></td></tr>`;
		// 		manBody.innerHTML += `<tr><td><div style="display:flex;align-items:center;gap:10px;"><img src="${img}" class="prod-img" onerror="this.src='${PLACEHOLDER}'"><b>${p.name}</b></div></td><td>${cat}</td><td><b>${p.qty}</b> ${unit}</td><td>${p.buyDate}</td><td>${p.exp}</td><td><small>${p.lastAction}</small></td><td><div class="action-group"><button class="btn btn-use" onclick='openUse(${rowData})'>⚡</button><button class="btn btn-outline" onclick='openEdit(${rowData})'>✏️</button><button class="btn btn-danger" onclick="deleteProduct(${p.id})">🗑️</button></div></td></tr>`;
		// 	});

		// 	// 3. Affichage: Box wahed l-koul Catégorie fih ga3 l-unités
		// 	statsSummary.innerHTML = '';
		// 	for (const catName in totals) {
		// 		let unitLines = '';
		// 		// Kandoro 3la ga3 l-unités li jm3na f had l-catégorie
		// 		for (const [unitName, sumValue] of Object.entries(totals[catName])) {
		// 			unitLines += `<div class="value">${sumValue.toFixed(2)}<span class="unit">${unitName}</span></div>`;
		// 		}

		// 		statsSummary.innerHTML += `
		//             <div class="stat-card">
		//                 <h3>Total ${catName}</h3>
		//                 ${unitLines}
		//             </div>`;
		// 	}
		// }
		async function loadProducts() {
			const response = await fetch('index2.php?fetch=1');
			const products = await response.json();

			const dashBody = document.querySelector('#dashTable tbody');
			const manBody = document.querySelector('#manTable tbody');
			const statsSummary = document.getElementById('statsSummary');

			dashBody.innerHTML = '';
			manBody.innerHTML = '';

			const totals = {};

			products.forEach(p => {
				const cat = p.catégorie || "Non classé";
				const unit = p.unit || "u";
				const qty = parseFloat(p.qty) || 0;
				const img = p.image ? p.image : PLACEHOLDER;

				if (!totals[cat]) totals[cat] = {};
				if (!totals[cat][unit]) totals[cat][unit] = 0;
				totals[cat][unit] += qty;

				// --- المنطق ديال الألوان والتعطيل ---
				let badgeClass = 'bg-ok';
				// هنا كنجهزو الزر على حسب الكمية
				let useBtnAttr = `class="btn btn-use" onclick='openUse(${JSON.stringify(p).replace(/'/g, "&apos;")})'`;

				if (qty <= 0) {
					badgeClass = 'bg-empty'; // أحمر
					useBtnAttr = `class="btn btn-disabled" disabled`; // تعطيل الزر حقيقي
				} else if (qty <= 5) {
					badgeClass = 'bg-low'; // برتقالي
				}

				const rowData = JSON.stringify(p).replace(/'/g, "&apos;");

				// الجدول الأول (Dashboard)
				dashBody.innerHTML += `<tr class="clickable" onclick='showDetails(${rowData})'>
            <td><div style="display:flex;align-items:center;gap:10px;"><img src="${img}" class="prod-img" onerror="this.src='${PLACEHOLDER}'"><b>${p.name}</b></div></td>
            <td>${cat}</td>
            <td><span class="badge ${badgeClass}">${p.qty} ${unit}</span></td>
            <td>${p.buyDate}</td>
            <td>${p.exp}</td>
            <td><small>${p.lastAction}</small></td>
        </tr>`;

				// الجدول الثاني (Gestion) - هنا فين كان الخطأ، ركز على الزر الأول:
				manBody.innerHTML += `<tr>
            <td><div style="display:flex;align-items:center;gap:10px;"><img src="${img}" class="prod-img" onerror="this.src='${PLACEHOLDER}'"><b>${p.name}</b></div></td>
            <td>${cat}</td>
            <td><b>${p.qty}</b> ${unit}</td>
            <td>${p.buyDate}</td>
            <td>${p.exp}</td>
            <td><small>${p.lastAction}</small></td>
            <td>
                <div class="action-group">
                    <button ${useBtnAttr}>⚡</button> 
                    <button class="btn btn-outline" onclick='openEdit(${rowData})'>✏️</button>
                    <button class="btn btn-danger" onclick="deleteProduct(${p.id})">🗑️</button>
                </div>
            </td>
        </tr>`;
			});

			// تحديث الإحصائيات
			statsSummary.innerHTML = '';
			for (const catName in totals) {
				let unitLines = '';
				for (const [unitName, sumValue] of Object.entries(totals[catName])) {
					unitLines += `<div class="value">${sumValue.toFixed(2)}<span class="unit">${unitName}</span></div>`;
				}
				statsSummary.innerHTML += `<div class="stat-card"><h3>Total ${catName}</h3>${unitLines}</div>`;
			}
		}
		// document.querySelectorAll('form').forEach(f => {
		// 	f.onsubmit = async (e) => {
		// 		e.preventDefault();
		// 		await fetch('index2.php', {
		// 			method: 'POST',
		// 			body: new FormData(f)
		// 		});
		// 		closeDrawer();
		// 		loadProducts();
		// 	};
		// });

		document.querySelectorAll('form').forEach(f => {
			f.onsubmit = async (e) => {
				e.preventDefault();

				try {
					const response = await fetch('index2.php', {
						method: 'POST',
						body: new FormData(f)
					});

					// كنحولوا الرد لـ JSON
					const result = await response.json();

					if (result.success) {
						// إذا نجحت العملية
						closeDrawer();
						loadProducts();
						// اختياري: تقدر تزيد ميساج ديال النجاح هنا
					} else {
						// إذا السيرفر صيفط success: false (بحال فاش كتكون الكمية كبر من السطوك)
						alert("⚠️ تنبيه: " + (result.error || result.message || "وقع خطأ ما"));
					}
				} catch (error) {
					console.error("Erreur:", error);
					alert("❌ فشل الاتصال بالسيرفر");
				}
			};
		});

		async function deleteProduct(id) {
			if (!confirm('Archiver ?')) return;
			const fd = new FormData();
			fd.append('action', 'delete');
			fd.append('id', id);
			await fetch('index2.php', {
				method: 'POST',
				body: fd
			});
			loadProducts();
		}

		function switchPage(id) {
			document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
			document.getElementById(id).classList.add('active');
		}

		function openAdd() {
			document.getElementById('saveForm').reset();
			document.getElementById('form_id').value = "";
			document.getElementById('drawer').classList.add('open');
			document.getElementById('overlay').style.display = "block";
		}

		function openEdit(p) {
			document.getElementById('form_id').value = p.id;
			document.getElementById('form_name').value = p.name;
			document.getElementById('form_cat').value = p.catégorie;
			document.getElementById('form_qty').value = p.qty;
			document.getElementById('form_unit').value = p.unit;
			document.getElementById('form_buy').value = p.buyDate;
			document.getElementById('form_exp').value = p.exp;
			document.getElementById('form_notes').value = p.notes;
			document.getElementById('drawer').classList.add('open');
			document.getElementById('overlay').style.display = "block";
		}

		function openUse(p) {
			document.getElementById('use_form_id').value = p.id;
			document.getElementById('use_form_date').value = new Date().toISOString().slice(0, 16);
			document.getElementById('useDrawer').classList.add('open');
			document.getElementById('overlay').style.display = "block";
			document.getElementById('currentStockDisplay').innerHTML = p.qty + " " + p.unit;
		}

		function closeDrawer() {
			document.querySelectorAll('.drawer').forEach(d => d.classList.remove('open'));
			document.getElementById('overlay').style.display = "none";
		}

		async function showDetails(p) {
			const img = p.image ? p.image : PLACEHOLDER;
			const content = document.getElementById('detailsContent');
			const expired = p.exp && new Date(p.exp) < new Date();
			content.innerHTML = `
                <img src="${img}" class="details-img" onerror="this.src='${PLACEHOLDER}'">
                <div class="info-item"><span class="info-label">Produit</span><span class="info-value">${p.name}</span></div>
                <div style="display:flex; gap:20px;">
                    <div class="info-item" style="flex:1;"><span class="info-label">Stock</span><span class="info-value">${p.qty} ${p.unit}</span></div>
                    <div class="info-item" style="flex:1;"><span class="info-label">Achat</span><span class="info-value">${p.buyDate}</span></div>
                </div>
                <div class="info-item"><span class="info-label">Expiration</span><span class="info-value" style="color:${expired ? 'red':'inherit'}">${p.exp || 'N/A'}</span></div>
                <div class="info-item"><span class="info-label">Historique des Actions</span><div id="logsTimeline" class="timeline">Chargement...</div></div>
                <div class="info-item"><span class="info-label">Notes</span><div class="notes-box">${p.notes || '...'}</div></div>
            `;
			openDrawer('detailsDrawer');
			try {
				const res = await fetch(`index2.php?fetch_logs=${p.id}`);
				const logs = await res.json();
				const container = document.getElementById('logsTimeline');
				container.innerHTML = logs.length ? logs.map(l => `
                    <div class="log-item">
                        <span class="log-date">${l.action_date}</span>
                        <b>${l.action_type}:</b> ${l.details}
                    </div>
                `).join('') : "Aucun historique.";
			} catch (e) {
				document.getElementById('logsTimeline').innerText = "Erreur.";
			}
		}

		function openDrawer(id) {
			document.getElementById(id).classList.add('open');
			document.getElementById('overlay').style.display = 'block';
		}
		async function loadCategories() {
			try {
				const response = await fetch('index2.php?fetch_categories=1');
				const categories = await response.json();
				const dataList = document.getElementById('category_options');

				// كنمصحو القائمة القديمة ونعمروها بالجديدة
				dataList.innerHTML = '';
				categories.forEach(cat => {
					const option = document.createElement('option');
					option.value = cat;
					dataList.appendChild(option);
				});
			} catch (e) {
				console.error("Erreur loading categories");
			}
		}

		// تعديل window.onload باش تخدمهم بجوج
		window.onload = () => {
			loadProducts();
			loadCategories();
		};
	</script>
</body>

</html>