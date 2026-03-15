<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');

$host = "localhost";
$dbname = "stocktable";
$user = "root";
$pass = "";

try {
	$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]);
} catch (PDOException $e) {
	die("Erreur de connexion : " . $e->getMessage());
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
		$sql = "INSERT INTO product_logs (product_id, product_name, catégorie, buyDate, qty, unit, action_type, details, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$pdo->prepare($sql)->execute([$p_id, $name, $cat, $buyDate, $qty, $unit, $type, $details, $notes]);
	}

	$action = $_POST['action'] ?? '';

	if ($action === 'save') {
		$id = $_POST['id'] ?? null;
		$name = $_POST['name'] ?? '';
		$cat = $_POST['cat'] ?? '';
		$buyDate = $_POST['buyDate'] ?? '';
		$qty = (isset($_POST['qty']) && $_POST['qty'] !== '') ? floatval($_POST['qty']) : 0;
		$unit = $_POST['unit'] ?? '';
		$exp = $_POST['exp'] ?? '';
		$notes = $_POST['notes'] ?? '';
		$imagePath = $_POST['existing_image'] ?? null;

		if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
			$tmpPath = $_FILES['image']['tmp_name'];
			$extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
			$newName = uniqid() . '.webp';
			$target = 'uploads/' . $newName;
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
			if (!$imgSuccess) {
				$rawTarget = 'uploads/' . uniqid() . '.' . $extension;
				if (move_uploaded_file($tmpPath, $rawTarget)) {
					$imagePath = $rawTarget;
				}
			}
		}

		if (!empty($name)) {
			$lastAction = !empty($id) ? "Modifié le " . date('d/m/Y') : "Ajouté le " . date('d/m/Y');
			if (!empty($id)) {
				$sql = "UPDATE products SET name=?, catégorie=?, buyDate=?, qty=?, unit=?, exp=?, notes=?, lastAction=?, image=? WHERE id=?";
				$params = [$name, $cat, $buyDate, $qty, $unit, $exp, $notes, $lastAction, $imagePath, $id];
			} else {
				$sql = "INSERT INTO products (name, catégorie, buyDate, first_qty, qty, unit, exp, notes, lastAction, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '1')";
				$params = [$name, $cat, $buyDate, $qty, $qty, $unit, $exp, $notes, $lastAction, $imagePath];
			}
			$pdo->prepare($sql)->execute($params);
		}
		echo json_encode(['success' => true]);
		exit;
	}

	if ($action === 'use') {
		$id = intval($_POST['id']);
		$used_qty = floatval($_POST['used_qty']);
		$formatted_date = date('d/m/Y à H:i', strtotime($_POST['use_date']));
		$s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
		$s->execute([$id]);
		$p = $s->fetch();
		if ($p) {
			$lastAction = "⚡ Utilisé ($used_qty {$p['unit']}) le $formatted_date";
			$pdo->prepare("UPDATE products SET qty = qty - ?, lastAction = ? WHERE id = ?")->execute([$used_qty, $lastAction, $id]);
			addLog($pdo, $id, $p['name'], $p['catégorie'], $p['buyDate'], $used_qty, $p['unit'], 'Utilisation', "Consommé le $formatted_date", $p['notes'] ?? '');
		}
	}

	if ($action === 'delete') {
		$id = intval($_POST['id']);
		$pdo->prepare("UPDATE products SET status = '0' WHERE id = ?")->execute([$id]);
	}
	echo json_encode(['success' => true]);
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AgriStock Pro - Inbat</title>
	<style>
		:root {
			--primary: #2d6a4f;
			--bg: #f4f7f6;
			--text: #1e293b;
			--danger: #ef4444;
			--gray: #64748b;
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

		.stat-card .value {
			font-size: 1.6rem;
			font-weight: bold;
			color: var(--primary);
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

		th,
		td {
			padding: 15px;
			text-align: left;
			border-bottom: 1px solid #f1f5f9;
		}

		tr.clickable {
			cursor: pointer;
			transition: 0.2s;
		}

		tr.clickable:hover {
			background: #f0fdf4;
		}

		.btn {
			padding: 8px 14px;
			border-radius: 8px;
			border: none;
			cursor: pointer;
			font-weight: 600;
		}

		.btn-primary {
			background: var(--primary);
			color: white;
		}

		.btn-outline {
			background: white;
			border: 1px solid var(--primary);
			color: var(--primary);
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

		.prod-img {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			object-fit: cover;
			background: #eee;
		}

		.badge {
			padding: 4px 10px;
			border-radius: 6px;
			font-size: 0.75rem;
			font-weight: bold;
			background: #e2e8f0;
		}

		.page-section {
			display: none;
		}

		.page-section.active {
			display: block;
		}

		input,
		select,
		textarea {
			width: 100%;
			padding: 10px;
			margin-top: 5px;
			border: 1px solid #ddd;
			border-radius: 6px;
		}

		.form-group {
			margin-bottom: 15px;
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
	</style>
</head>

<body>
	<div class="overlay" id="overlay" onclick="closeDrawer()"></div>
	<div class="drawer" id="drawer">
		<h2>Produit</h2>
		<form id="saveForm">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" id="form_id">
			<input type="hidden" name="existing_image" id="form_existing_image">
			<div class="form-group"><label>Image</label><input type="file" name="image" accept="image/*"></div>
			<div class="form-group"><label>Nom</label><input type="text" name="name" id="form_name" required></div>
			<div class="form-group"><label>Catégorie</label><input type="text" name="cat" id="form_cat" list="category_options"></div>
			<div style="display:flex; gap:10px;">
				<div class="form-group"><label>Qty</label><input type="number" name="qty" id="form_qty" step="0.01" required></div>
				<div class="form-group"><label>Unité</label><input type="text" name="unit" id="form_unit" required></div>
			</div>
			<div class="form-group"><label>Achat</label><input type="date" name="buyDate" id="form_buy" required></div>
			<div class="form-group"><label>Exp</label><input type="date" name="exp" id="form_exp"></div>
			<div class="form-group"><label>Notes</label><textarea name="notes" id="form_notes"></textarea></div>
			<button type="submit" class="btn btn-primary" style="width:100%;">💾 Enregistrer</button>
		</form>
	</div>
	<div class="drawer" id="useDrawer">
		<h2>⚡ Utiliser</h2>
		<div id="currentStockDisplay" style="text-align:center; font-size:20px; font-weight:bold; margin:20px 0;"></div>
		<form id="useForm">
			<input type="hidden" name="action" value="use">
			<input type="hidden" name="id" id="use_form_id">
			<div class="form-group"><label>Quantité</label><input type="number" name="used_qty" step="0.01" required></div>
			<div class="form-group"><label>Date</label><input type="datetime-local" name="use_date" id="use_form_date" required></div>
			<button type="submit" class="btn btn-primary" style="width:100%;">✓ Valider</button>
		</form>
	</div>
	<div class="drawer" id="detailsDrawer">
		<div style="display:flex; justify-content: space-between; align-items: center; margin-bottom:20px;">
			<h2>Détails</h2>
			<button class="btn" onclick="closeDrawer()" style="background:#eee;">✕</button>
		</div>
		<div id="detailsContent"></div>
	</div>
	<nav>
		<div style="font-size:32px; margin-top:20px;">🌾</div>
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
			<div class="table-container">
				<table id="dashTable">
					<thead>
						<tr>
							<th>Produit</th>
							<th>Catégorie</th>
							<th>Stock</th>
							<th>Achat</th>
							<th>Exp</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</section>
		<section id="manage" class="page-section">
			<button class="btn btn-primary" onclick="openAdd()" style="margin-bottom:20px;">+ Nouveau</button>
			<div class="table-container">
				<table id="manTable">
					<thead>
						<tr>
							<th>Nom</th>
							<th>Catégorie</th>
							<th>Stock</th>
							<th>Achat</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</section>
	</main>
	<datalist id="category_options">
		<option value="Alaf">
		<option value="Dwa">
		<option value="Engrais">
		<option value="Semences">
	</datalist>
	<script>
		const API_URL = window.location.pathname;
		const PLACEHOLDER = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2250%22%20height%3D%2250%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23eee%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20text-anchor%3D%22middle%22%20fill%3D%22%23aaa%22%3ENo%20Img%3C%2Ftext%3E%3C%2Fsvg%3E';
		async function loadProducts() {
			const res = await fetch(API_URL + '?fetch=1');
			const data = await res.json();
			const dashBody = document.querySelector('#dashTable tbody');
			const manBody = document.querySelector('#manTable tbody');
			const stats = document.getElementById('statsSummary');
			dashBody.innerHTML = manBody.innerHTML = stats.innerHTML = '';
			const totals = {};
			data.forEach(p => {
				const img = p.image ? p.image : PLACEHOLDER;
				const pData = JSON.stringify(p).replace(/'/g, "&apos;");
				const commonCells = `<td><div style="display:flex;align-items:center;gap:10px;"><img src="${img}" class="prod-img" onerror="this.src='${PLACEHOLDER}'"><b>${p.name}</b></div></td><td>${p.catégorie}</td><td><span class="badge">${p.qty} ${p.unit}</span></td><td>${p.buyDate}</td>`;
				dashBody.innerHTML += `<tr class="clickable" onclick='showDetails(${pData})'>${commonCells}<td>${p.exp || '-'}</td></tr>`;
				manBody.innerHTML += `<tr class="clickable" onclick='showDetails(${pData})'>${commonCells}<td>
                    <button class="btn btn-outline" onclick='event.stopPropagation(); openUse(${pData})'>⚡</button>
                    <button class="btn btn-outline" onclick='event.stopPropagation(); openEdit(${pData})'>✏️</button>
                </td></tr>`;
				if (!totals[p.catégorie]) totals[p.catégorie] = 0;
				totals[p.catégorie] += parseFloat(p.qty);
			});
			for (let cat in totals) {
				stats.innerHTML += `<div class="stat-card"><h3>${cat}</h3><div class="value">${totals[cat]}</div></div>`;
			}
		}
		document.querySelectorAll('form').forEach(f => {
			f.onsubmit = async (e) => {
				e.preventDefault();
				await fetch(API_URL, {
					method: 'POST',
					body: new FormData(f)
				});
				closeDrawer();
				loadProducts();
			};
		});
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
				const res = await fetch(`${API_URL}?fetch_logs=${p.id}`);
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

		function openAdd() {
			document.getElementById('saveForm').reset();
			document.getElementById('form_id').value = '';
			openDrawer('drawer');
		}

		function openEdit(p) {
			document.getElementById('form_id').value = p.id;
			document.getElementById('form_name').value = p.name;
			document.getElementById('form_cat').value = p.catégorie;
			document.getElementById('form_qty').value = p.qty;
			document.getElementById('form_unit').value = p.unit;
			document.getElementById('form_buy').value = p.buyDate;
			document.getElementById('form_exp').value = p.exp;
			document.getElementById('form_existing_image').value = p.image || '';
			openDrawer('drawer');
		}

		function openUse(p) {
			document.getElementById('use_form_id').value = p.id;
			document.getElementById('currentStockDisplay').innerText = p.qty + " " + p.unit;
			document.getElementById('use_form_date').value = new Date().toISOString().slice(0, 16);
			openDrawer('useDrawer');
		}

		function openDrawer(id) {
			document.getElementById(id).classList.add('open');
			document.getElementById('overlay').style.display = 'block';
		}

		function closeDrawer() {
			document.querySelectorAll('.drawer').forEach(d => d.classList.remove('open'));
			document.getElementById('overlay').style.display = 'none';
		}

		function switchPage(id) {
			document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
			document.getElementById(id).classList.add('active');
		}
		window.onload = loadProducts;
	</script>
</body>

</html>