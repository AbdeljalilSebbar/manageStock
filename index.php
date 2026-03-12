
<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "stocktable";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Logic for POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SAVE / EDIT LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $cat = $_POST['cat'];
        $buyDate = $_POST['buyDate'];
        $qty = $_POST['qty'];
        $unit = $_POST['unit'];
        $exp = $_POST['exp'];
        $notes = $_POST['notes'];
        $lastAction = !empty($id) ? "Modifié le " . date('d/m/Y') : "Ajouté le " . date('d/m/Y');

        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE products SET name=?, catégorie=?, buyDate=?, qty=?, unit=?, exp=?, notes=?, lastAction=? WHERE id=?");
            $stmt->bind_param("sssdssssi", $name, $cat, $buyDate, $qty, $unit, $exp, $notes, $lastAction, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, catégorie, buyDate, qty, unit, exp, notes, lastAction, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssdssss", $name, $cat, $buyDate, $qty, $unit, $exp, $notes, $lastAction);
        }
        $stmt->execute();
    }

    // 2. USE LOGIC (NEW)
    if (isset($_POST['action']) && $_POST['action'] === 'use') {
        $id = intval($_POST['id']);
        $used_qty = floatval($_POST['used_qty']);
        $use_date = $_POST['use_date']; // Format: YYYY-MM-DDTHH:MM
        
        $formatted_date = date('d/m/Y à H:i', strtotime($use_date));
        
        // Fetch current unit to make the text beautiful
        $res = $conn->query("SELECT unit FROM products WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            $unit = $row['unit'];
            $lastAction = "⚡ Utilisé ($used_qty $unit) le $formatted_date";
            
            // Subtract quantity and update lastAction
            $stmt = $conn->prepare("UPDATE products SET qty = qty - ?, lastAction = ? WHERE id = ?");
            $stmt->bind_param("dsi", $used_qty, $lastAction, $id);
            $stmt->execute();
        }
    }

    // 3. SOFT DELETE LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("UPDATE products SET status = 0 WHERE id=$id");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- Data Fetching (ONLY WHERE STATUS = 1) ---
$res = $conn->query("SELECT * FROM products WHERE status = 1 ORDER BY id DESC");
$products = [];
$categoryTotals = [];

if ($res) {
    while($row = $res->fetch_assoc()) {
        $products[] = $row;
        $c = $row['catégorie'];
        $u = $row['unit'];
        if (!isset($categoryTotals[$c][$u])) $categoryTotals[$c][$u] = 0;
        $categoryTotals[$c][$u] += $row['qty'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriStock Pro</title>
    <style>
        :root { --primary: #2d6a4f; --secondary: #52b788; --bg: #f4f7f6; --white: #ffffff; --text: #1e293b; --danger: #ef4444; --warning: #f59e0b; --gray: #64748b; --success: #16a34a; --use-color: #0ea5e9; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        nav { width: 80px; background: var(--primary); display: flex; flex-direction: column; align-items: center; padding: 20px 0; color: white; flex-shrink: 0; }
        main { flex: 1; padding: 30px; overflow-y: auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #f8fafc; padding: 15px; text-align: left; color: var(--gray); font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; vertical-align: middle; }
        
        .btn { padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: 0.2s; text-decoration: none; font-size: 0.85rem;}
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background: white; color: var(--primary); border: 1px solid var(--primary); }
        .btn-danger { background: #fee2e2; color: var(--danger); }
        .btn-danger:hover { background: var(--danger); color: white; }
        .btn-use { background: #e0f2fe; color: var(--use-color); border: 1px solid #bae6fd; }
        .btn-use:hover { background: var(--use-color); color: white; }
        
        .search-input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; width: 300px; margin-bottom: 15px; font-size: 0.9rem; outline: none; transition: 0.2s; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.2); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 15px; border-radius: 12px; border-left: 5px solid var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; }
        .bg-ok { background: #dcfce7; color: var(--success); }
        .bg-low { background: #ffedd5; color: var(--warning); }
        
        .drawer { position: fixed; top: 0; right: -500px; width: 400px; height: 100vh; background: white; box-shadow: -5px 0 25px rgba(0,0,0,0.1); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 30px; z-index: 100; overflow-y: auto; }
        .drawer.open { right: 0; }
        .overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); display: none; z-index: 90; backdrop-filter: blur(2px); }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--gray); }
        input[type="text"]:not(.search-input), input[type="number"], input[type="date"], input[type="datetime-local"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); outline: none; }
        
        .stock-info-box { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .stock-info-box span { font-size: 1.5rem; font-weight: bold; color: var(--primary); display: block; margin-top: 5px;}
        
        .page-section { display: none; }
        .active { display: block; }
        .action-group { display: flex; gap: 5px; }
    </style>
</head>
<body>

    <div class="overlay" id="overlay" onclick="closeDrawer()"></div>

    <div class="drawer" id="drawer">
        <h2 id="drawerTitle">Produit</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="form_id">
            <div class="form-group"><label>Nom du Produit</label><input type="text" name="name" id="form_name" required></div>
            <div class="form-group"><label>Catégorie</label>
                <select name="cat" id="form_cat">
                    <option>Alaf / Aliments bétail</option>
                    <option>Dwa / Médicaments vétérinaires</option>
                    <option>Engrais / Fertilizers</option>
                    <option>Semences</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <div class="form-group" style="flex:2;"><label>Quantité</label><input type="number" name="qty" id="form_qty" step="0.01" required></div>
                <div class="form-group" style="flex:1;"><label>Unité</label>
                    <select name="unit" id="form_unit">
                        <option>KG</option><option>G</option><option>Tonnes</option>
                        <option>L</option><option>ml</option><option>Sacs</option><option>pieces</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Date d'Achat</label><input type="date" name="buyDate" id="form_buy"></div>
            <div class="form-group"><label>Date d'Expiration</label><input type="date" name="exp" id="form_exp"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="form_notes" rows="3"></textarea></div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">💾 Enregistrer</button>
        </form>
    </div>

    <div class="drawer" id="useDrawer">
        <h2 id="useTitle" style="color: var(--use-color);">⚡ Utiliser Produit</h2>
        
        <div class="stock-info-box">
            Stock Disponible:
            <span id="currentStockDisplay">0 KG</span>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="use">
            <input type="hidden" name="id" id="use_form_id">
            
            <div class="form-group">
                <label>Quantité à retirer du stock</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" name="used_qty" id="use_form_qty" step="0.01" min="0.01" required style="flex: 2;">
                    <div id="use_unit_display" style="flex: 1; font-weight: bold; color: var(--gray);">KG</div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Date et Heure de l'utilisation</label>
                <input type="datetime-local" name="use_date" id="use_form_date" required>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; background: var(--use-color); color: white; padding: 12px; font-size: 1rem; margin-top: 20px;">✓ Valider l'utilisation</button>
        </form>
    </div>

    <nav><div style="font-size: 32px; margin-top: 20px;">🌾</div></nav>

    <main>
        <header>
            <h1>AgriStock Pro</h1>
            <div>
                <button class="btn btn-outline" onclick="switchPage('dashboard')">📊 Dashboard</button> 
                <button class="btn btn-primary" onclick="switchPage('manage')">⚙️ Gestion</button>
            </div>
        </header>

        <section id="dashboard" class="page-section active">
            <div class="stats-grid">
                <?php foreach($categoryTotals as $catName => $units): ?>
                <div class="stat-card">
                    <small><?php echo htmlspecialchars($catName); ?></small>
                    <div><?php foreach($units as $unit => $total) echo "<b>$total</b> $unit<br>"; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <input type="text" class="search-input" placeholder="🔍 Rechercher dans le tableau de bord..." onkeyup="searchTable(this, 'dashTable')">
            
            <div class="table-container">
                <table id="dashTable">
                    <thead>
                        <tr><th>Produit</th><th>Catégorie</th><th>Stock</th><th>Achat</th><th>Exp</th><th>Dernière Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $p): 
                            $badgeClass = $p['qty'] <= 5 ? 'bg-low' : 'bg-ok'; // Slight visual cue if stock is low
                        ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($p['name']); ?></b></td>
                            <td><?php echo htmlspecialchars($p['catégorie']); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $p['qty']." ".$p['unit']; ?></span></td>
                            <td><?php echo $p['buyDate']; ?></td>
                            <td><?php echo $p['exp']; ?></td>
                            <td><small style="color: var(--gray);"><?php echo htmlspecialchars($p['lastAction']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="manage" class="page-section">
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                <h2>Inventaire & Actions</h2>
                <input type="text" class="search-input" style="margin-bottom: 0;" placeholder="🔍 Rechercher un produit..." onkeyup="searchTable(this, 'manTable')">
                <button class="btn btn-primary" onclick="openAdd()">+ Nouveau Produit</button>
            </div>
            <div class="table-container">
                <table id="manTable">
                    <thead>
                        <tr><th>Nom</th><th>Catégorie</th><th>Stock</th><th>Date Achat</th> <th>Date Exp</th><th>Dernière Action</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($p['name']); ?></b></td>
                            <td><?php echo htmlspecialchars($p['catégorie']); ?></td>
                            <td><b><?php echo $p['qty']."</b> ".$p['unit']; ?></td>
                            <td style="color: var(--gray); font-size: 0.85rem;"><?php echo $p['buyDate']; ?></td> <td style="color: var(--danger); font-size: 0.85rem; font-weight: 500;"><?php echo $p['exp']; ?></td>
                            <td><small><?php echo htmlspecialchars($p['lastAction']); ?></small></td>
                            <td>
                                <div class="action-group">
                                    <button class="btn btn-use" onclick='openUse(<?php echo json_encode($p); ?>)' title="Utiliser du stock">⚡ Utiliser</button>
                                    
                                    <button class="btn btn-outline" onclick='openEdit(<?php echo json_encode($p); ?>)'>✏️</button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Archiver ce produit ?')" title="Supprimer">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Real-time search function
        function searchTable(input, tableId) {
            let filter = input.value.toUpperCase();
            let table = document.getElementById(tableId);
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let row = tr[i];
                let textValue = row.textContent || row.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        function switchPage(id) {
            document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
            document.getElementById(id).classList.add('active');
        }

        // Add Product Drawer
        function openAdd() {
            document.getElementById('drawerTitle').innerText = "Nouveau Produit";
            document.getElementById('form_id').value = "";
            document.getElementById('form_name').value = "";
            document.getElementById('form_qty').value = "";
            document.getElementById('form_buy').value = "";
            document.getElementById('form_exp').value = "";
            document.getElementById('form_notes').value = "";
            document.getElementById('drawer').classList.add('open');
            document.getElementById('overlay').style.display = "block";
        }

        // Edit Product Drawer
        function openEdit(product) {
            document.getElementById('drawerTitle').innerText = "Modifier " + product.name;
            document.getElementById('form_id').value = product.id;
            document.getElementById('form_name').value = product.name;
            document.getElementById('form_cat').value = product.catégorie; 
            document.getElementById('form_qty').value = product.qty;
            document.getElementById('form_unit').value = product.unit;
            document.getElementById('form_buy').value = product.buyDate;
            document.getElementById('form_exp').value = product.exp;
            document.getElementById('form_notes').value = product.notes;
            document.getElementById('drawer').classList.add('open');
            document.getElementById('overlay').style.display = "block";
        }

        // NEW: Use Product Drawer
        function openUse(product) {
            document.getElementById('useTitle').innerText = "⚡ Utiliser: " + product.name;
            document.getElementById('use_form_id').value = product.id;
            
            // Display current stock nicely
            document.getElementById('currentStockDisplay').innerText = product.qty + " " + product.unit;
            document.getElementById('use_unit_display').innerText = product.unit;
            
            // Restrict maximum quantity so they can't use more than they have
            let qtyInput = document.getElementById('use_form_qty');
            qtyInput.max = product.qty;
            qtyInput.value = ""; // clear previous inputs
            
            // Set default date and time to right now
            let now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('use_form_date').value = now.toISOString().slice(0,16);
            
            document.getElementById('useDrawer').classList.add('open');
            document.getElementById('overlay').style.display = "block";
        }

        function closeDrawer() {
            document.getElementById('drawer').classList.remove('open');
            document.getElementById('useDrawer').classList.remove('open');
            document.getElementById('overlay').style.display = "none";
        }
    </script>
</body>
</html>