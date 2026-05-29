<?php
// ==========================================
// QC RETURN MANAGEMENT SYSTEM
// Database: return_qc (Main database with all tables)
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Main database connection (return_qc)
$host = '127.0.0.1';
$db   = 'return_qc';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_PERSISTENT         => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Function to generate auto reference number (YYYYMMDDXXXXX format)
function generateReferenceNumber($pdo) {
    $date = date('Ymd');
    $prefix = $date;
    
    $stmt = $pdo->prepare("SELECT reference_number FROM qc_damage_main WHERE reference_number LIKE ? ORDER BY record_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $last_num = intval(substr($last['reference_number'], -5));
        $new_num = $last_num + 1;
        $new_ref = $prefix . str_pad($new_num, 5, '0', STR_PAD_LEFT);
    } else {
        $new_ref = $prefix . '00001';
    }
    
    return $new_ref;
}

// Create uploads directory if not exists
$upload_dir = 'uploads/qc_returns/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Large data optimization
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    try {
        // Fetch Suppliers (with search)
        if ($action === 'fetch_suppliers') {
            $search = $_POST['search'] ?? '';
            $sql = "SELECT supplier_id, supplier_name, system_id, contact_number FROM suppliers";
            if (!empty($search)) {
                $sql .= " WHERE supplier_name LIKE ? OR system_id LIKE ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$search%", "%$search%"]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $suppliers = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $suppliers]);
            exit;
        }
        
        // Fetch Invoices for selected supplier with search
        if ($action === 'fetch_invoices') {
            $supplier_id = $_POST['supplier_id'];
            $search_invoice = $_POST['search_invoice'] ?? '';
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT si.invoice_id, si.invoice_number, si.invoice_date, si.checked_date, si.checker_name,
                       COUNT(ii.invoice_item_id) as total_items,
                       COALESCE(SUM(ii.received_qty), 0) as total_received,
                       COALESCE(SUM(ii.defect_qty), 0) as total_defects,
                       COALESCE(SUM(ii.return_qty), 0) as total_returns
                FROM supplier_invoices si
                LEFT JOIN invoice_items ii ON si.invoice_id = ii.invoice_id
                WHERE si.supplier_id = ?
            ";
            
            $params = [$supplier_id];
            
            if (!empty($search_invoice)) {
                $sql .= " AND si.invoice_number LIKE ?";
                $params[] = "%$search_invoice%";
            }
            
            $sql .= " GROUP BY si.invoice_id ORDER BY si.invoice_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();
            
            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT si.invoice_id) as total 
                FROM supplier_invoices si
                WHERE si.supplier_id = ?
            ";
            $countParams = [$supplier_id];
            if (!empty($search_invoice)) {
                $countSql .= " AND si.invoice_number LIKE ?";
                $countParams[] = "%$search_invoice%";
            }
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            echo json_encode([
                'status' => 'success', 
                'data' => $invoices,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_records' => $total,
                    'has_more' => ($page * $limit) < $total
                ]
            ]);
            exit;
        }
        
        // Fetch Invoice Items for selected invoice
        if ($action === 'fetch_invoice_items') {
            $invoice_id = $_POST['invoice_id'];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT ii.invoice_item_id, i.item_id, i.item_name, i.item_code, i.system_id,
                       ii.received_qty, ii.checked_sample_qty, ii.defect_qty, ii.return_qty, ii.status,
                       i.cost_price, i.selling_price
                FROM invoice_items ii
                LEFT JOIN items i ON ii.item_id = i.item_id
                WHERE ii.invoice_id = ? AND ii.return_qty > 0
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$invoice_id, $limit, $offset]);
            $items = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM invoice_items ii
                WHERE ii.invoice_id = ? AND ii.return_qty > 0
            ");
            $countStmt->execute([$invoice_id]);
            $total = $countStmt->fetch()['total'];
            
            echo json_encode([
                'status' => 'success', 
                'data' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_records' => $total,
                    'has_more' => ($page * $limit) < $total
                ]
            ]);
            exit;
        }
        
        // Get Item Cost Price
        if ($action === 'get_item_cost') {
            $item_code = $_POST['item_code'];
            $stmt = $pdo->prepare("SELECT cost_price, selling_price, item_name FROM items WHERE item_code = ?");
            $stmt->execute([$item_code]);
            $item = $stmt->fetch();
            echo json_encode(['status' => 'success', 'cost_price' => $item['cost_price'] ?? 0, 'selling_price' => $item['selling_price'] ?? 0, 'item_name' => $item['item_name'] ?? '']);
            exit;
        }
        
        // Create QC Return Record
        if ($action === 'create_qc_return') {
            $pdo->beginTransaction();
            
            $record_date = $_POST['record_date'];
            $supplier_id = $_POST['supplier_id'];
            $invoice_number = $_POST['invoice_number'];
            $reference_number = generateReferenceNumber($pdo);
            $doc_number = $_POST['doc_number'] ?? '';
            $mode_id = $_POST['mode_id'];
            $reason_id = $_POST['reason_id'];
            $added_by_user = $_POST['added_by_user'];
            $selected_items = json_decode($_POST['selected_items'], true);
            $remarks = $_POST['remarks'] ?? '';
            
            // Insert main record
            $stmt = $pdo->prepare("
                INSERT INTO qc_damage_main 
                (record_date, supplier_id, invoice_number, reference_number, doc_number, mode_id, reason_id, added_by_user, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$record_date, $supplier_id, $invoice_number, $reference_number, $doc_number, $mode_id, $reason_id, $added_by_user, $remarks]);
            $record_id = $pdo->lastInsertId();
            
            // Batch insert items
            if (count($selected_items) > 0) {
                $sql = "INSERT INTO qc_damage_items (record_id, item_code, item_name, quantity, unit_cost, total_cost) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($selected_items as $item) {
                    $quantity = intval($item['quantity']);
                    $unit_cost = floatval($item['unit_cost']);
                    $total_cost = $quantity * $unit_cost;
                    $values[] = "(?, ?, ?, ?, ?, ?)";
                    $params[] = $record_id;
                    $params[] = $item['item_code'];
                    $params[] = $item['item_name'] ?? '';
                    $params[] = $quantity;
                    $params[] = $unit_cost;
                    $params[] = $total_cost;
                }
                
                $sql .= implode(", ", $values);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'record_id' => $record_id, 'reference_number' => $reference_number]);
            exit;
        }
        
        // Update Return Items
        if ($action === 'update_return_items') {
            $record_id = $_POST['record_id'];
            $updated_items = json_decode($_POST['updated_items'], true);
            
            $pdo->beginTransaction();
            
            foreach ($updated_items as $item) {
                $quantity = intval($item['quantity']);
                $unit_cost = floatval($item['unit_cost']);
                $total_cost = $quantity * $unit_cost;
                
                $stmt = $pdo->prepare("
                    UPDATE qc_damage_items 
                    SET quantity = ?, unit_cost = ?, total_cost = ?
                    WHERE item_id = ? AND record_id = ?
                ");
                $stmt->execute([$quantity, $unit_cost, $total_cost, $item['item_id'], $record_id]);
            }
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Items updated successfully']);
            exit;
        }
        
        // Upload Images
        if ($action === 'upload_images') {
            $record_id = $_POST['record_id'];
            $uploaded_files = [];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qc_item_images WHERE record_id = ?");
            $stmt->execute([$record_id]);
            $existing_count = $stmt->fetch()['count'];
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_images = 8;
            $max_file_size = 5 * 1024 * 1024;
            $remaining_slots = $max_images - $existing_count;
            
            if (count($_FILES['images']['name']) > $remaining_slots) {
                throw new Exception("Maximum {$max_images} images allowed. You already have {$existing_count} images.");
            }
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES['images']['type'][$key];
                    $file_size = $_FILES['images']['size'][$key];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        continue;
                    }
                    if ($file_size > $max_file_size) {
                        throw new Exception("File " . $_FILES['images']['name'][$key] . " exceeds 5MB limit");
                    }
                    
                    $original_name = $_FILES['images']['name'][$key];
                    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $unique_filename = 'QC_' . uniqid() . '_' . time() . '_' . $key . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO qc_item_images (record_id, image_path, image_name, image_size, image_type, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$record_id, $file_path, $original_name, $file_size, $file_type, $_POST['uploaded_by'] ?? 'QC Officer']);
                        $uploaded_files[] = $file_path;
                    }
                }
            }
            
            echo json_encode(['status' => 'success', 'message' => count($uploaded_files) . ' images uploaded']);
            exit;
        }
        
        // Fetch QC Returns List
        if ($action === 'fetch_qc_returns') {
            $where = [];
            $params = [];
            
            if (!empty($_POST['supplier_id'])) {
                $where[] = "dm.supplier_id = ?";
                $params[] = $_POST['supplier_id'];
            }
            if (!empty($_POST['date_from'])) {
                $where[] = "DATE(dm.record_date) >= ?";
                $params[] = $_POST['date_from'];
            }
            if (!empty($_POST['date_to'])) {
                $where[] = "DATE(dm.record_date) <= ?";
                $params[] = $_POST['date_to'];
            }
            if (!empty($_POST['record_id'])) {
                $where[] = "dm.record_id = ?";
                $params[] = $_POST['record_id'];
            }
            if (!empty($_POST['invoice_number'])) {
                $where[] = "dm.invoice_number LIKE ?";
                $params[] = '%' . $_POST['invoice_number'] . '%';
            }
            if (!empty($_POST['reference_number'])) {
                $where[] = "dm.reference_number LIKE ?";
                $params[] = '%' . $_POST['reference_number'] . '%';
            }
            if (!empty($_POST['mode_id'])) {
                $where[] = "dm.mode_id = ?";
                $params[] = $_POST['mode_id'];
            }
            if (!empty($_POST['reason_id'])) {
                $where[] = "dm.reason_id = ?";
                $params[] = $_POST['reason_id'];
            }
            
            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 25;
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT SQL_CALC_FOUND_ROWS dm.*, 
                       COUNT(DISTINCT di.item_id) as total_items,
                       COALESCE(SUM(di.quantity), 0) as total_quantity,
                       COALESCE(SUM(di.total_cost), 0) as total_cost,
                       COUNT(DISTINCT img.image_id) as total_images,
                       m.mode_name,
                       s.supplier_name,
                       rr.reason_text
                FROM qc_damage_main dm
                LEFT JOIN qc_damage_items di ON dm.record_id = di.record_id
                LEFT JOIN qc_item_images img ON dm.record_id = img.record_id
                LEFT JOIN qc_modes m ON dm.mode_id = m.mode_id
                LEFT JOIN suppliers s ON dm.supplier_id = s.supplier_id
                LEFT JOIN return_reasons rr ON dm.reason_id = rr.reason_id
                $whereClause
                GROUP BY dm.record_id
                ORDER BY dm.record_id DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $returns = $stmt->fetchAll();
            
            $countStmt = $pdo->query("SELECT FOUND_ROWS() as total");
            $total = $countStmt->fetch()['total'];
            
            echo json_encode([
                'status' => 'success', 
                'data' => $returns,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_records' => $total,
                    'limit' => $limit,
                    'has_more' => ($page * $limit) < $total
                ]
            ]);
            exit;
        }
        
        // Get QC Return Details
        if ($action === 'get_qc_return_details') {
            $record_id = $_POST['record_id'];
            
            $stmt = $pdo->prepare("
                SELECT dm.*, m.mode_name, s.supplier_name, rr.reason_text
                FROM qc_damage_main dm
                LEFT JOIN qc_modes m ON dm.mode_id = m.mode_id
                LEFT JOIN suppliers s ON dm.supplier_id = s.supplier_id
                LEFT JOIN return_reasons rr ON dm.reason_id = rr.reason_id
                WHERE dm.record_id = ?
            ");
            $stmt->execute([$record_id]);
            $main = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
            $stmt->execute([$record_id]);
            $items = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ?");
            $stmt->execute([$record_id]);
            $images = $stmt->fetchAll();
            
            echo json_encode(['status' => 'success', 'main' => $main, 'items' => $items, 'images' => $images]);
            exit;
        }
        
        // Update QC Return Status
        if ($action === 'update_return_status') {
            $record_id = $_POST['record_id'];
            $field = $_POST['field'];
            $value = $_POST['value'];
            $user = $_POST['user'];
            $datetime = date('Y-m-d H:i:s');
            
            $allowed_fields = ['is_informed', 'is_store_received', 'is_gate_cleared', 'is_handover_complete'];
            if (!in_array($field, $allowed_fields)) {
                throw new Exception("Invalid field");
            }
            
            $update_fields = [];
            $params = [];
            
            if ($field === 'is_informed') {
                $update_fields[] = "is_informed = ?";
                $update_fields[] = "informed_by_user = ?";
                $update_fields[] = "informed_datetime = ?";
                $params = [$value, $user, $datetime];
            } elseif ($field === 'is_store_received') {
                $update_fields[] = "is_store_received = ?";
                $update_fields[] = "store_user = ?";
                $update_fields[] = "store_datetime = ?";
                $params = [$value, $user, $datetime];
            } elseif ($field === 'is_gate_cleared') {
                $update_fields[] = "is_gate_cleared = ?";
                $update_fields[] = "gate_user = ?";
                $update_fields[] = "gate_datetime = ?";
                $params = [$value, $user, $datetime];
            } elseif ($field === 'is_handover_complete') {
                $update_fields[] = "is_handover_complete = ?";
                $update_fields[] = "handover_user = ?";
                $update_fields[] = "handover_datetime = ?";
                $params = [$value, $user, $datetime];
            }
            
            $params[] = $record_id;
            $sql = "UPDATE qc_damage_main SET " . implode(", ", $update_fields) . " WHERE record_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);
            exit;
        }
        
        // Get Modes
        if ($action === 'get_modes') {
            $search = $_POST['search'] ?? '';
            $sql = "SELECT mode_id, mode_name FROM qc_modes";
            if (!empty($search)) {
                $sql .= " WHERE mode_name LIKE ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$search%"]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $modes = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $modes]);
            exit;
        }
        
        // Get Return Reasons (fixed - removed category column)
        if ($action === 'get_return_reasons') {
            $search = $_POST['search'] ?? '';
            $sql = "SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_text";
            if (!empty($search)) {
                $sql = "SELECT reason_id, reason_text FROM return_reasons WHERE reason_text LIKE ? ORDER BY reason_text";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$search%"]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $reasons = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $reasons]);
            exit;
        }
        
        // Export Returns to CSV
        if ($action === 'export_returns') {
            $where = [];
            $params = [];
            
            if (!empty($_POST['supplier_id'])) {
                $where[] = "dm.supplier_id = ?";
                $params[] = $_POST['supplier_id'];
            }
            if (!empty($_POST['date_from'])) {
                $where[] = "DATE(dm.record_date) >= ?";
                $params[] = $_POST['date_from'];
            }
            if (!empty($_POST['date_to'])) {
                $where[] = "DATE(dm.record_date) <= ?";
                $params[] = $_POST['date_to'];
            }
            
            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            $sql = "
                SELECT dm.record_id, dm.reference_number, dm.record_date, dm.invoice_number, 
                       s.supplier_name, dm.doc_number, m.mode_name, rr.reason_text, dm.remarks,
                       COALESCE(SUM(di.quantity), 0) as total_quantity,
                       COALESCE(SUM(di.total_cost), 0) as total_cost,
                       dm.is_informed, dm.is_store_received, dm.is_gate_cleared, dm.is_handover_complete,
                       dm.informed_datetime, dm.store_datetime, dm.gate_datetime, dm.handover_datetime
                FROM qc_damage_main dm
                LEFT JOIN qc_damage_items di ON dm.record_id = di.record_id
                LEFT JOIN qc_modes m ON dm.mode_id = m.mode_id
                LEFT JOIN suppliers s ON dm.supplier_id = s.supplier_id
                LEFT JOIN return_reasons rr ON dm.reason_id = rr.reason_id
                $whereClause
                GROUP BY dm.record_id
                ORDER BY dm.record_id DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $returns = $stmt->fetchAll();
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="qc_returns_export_' . date('Ymd_His') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Return ID', 'Reference', 'Date', 'Invoice', 'Supplier', 'Doc Number', 'Mode', 'Reason', 'Remarks', 'Total Qty', 'Total Cost (LKR)', 'Informed', 'Store Received', 'Gate Cleared', 'Handover Complete', 'Informed Date', 'Store Date', 'Gate Date', 'Handover Date']);
            
            foreach ($returns as $row) {
                fputcsv($output, [
                    $row['record_id'],
                    $row['reference_number'],
                    $row['record_date'],
                    $row['invoice_number'],
                    $row['supplier_name'],
                    $row['doc_number'],
                    $row['mode_name'],
                    $row['reason_text'],
                    $row['remarks'],
                    $row['total_quantity'],
                    $row['total_cost'],
                    $row['is_informed'] ? 'Yes' : 'No',
                    $row['is_store_received'] ? 'Yes' : 'No',
                    $row['is_gate_cleared'] ? 'Yes' : 'No',
                    $row['is_handover_complete'] ? 'Yes' : 'No',
                    $row['informed_datetime'],
                    $row['store_datetime'],
                    $row['gate_datetime'],
                    $row['handover_datetime']
                ]);
            }
            fclose($output);
            exit;
        }
        
        // Delete Return Record
        if ($action === 'delete_return') {
            $record_id = $_POST['record_id'];
            
            $pdo->beginTransaction();
            
            // Get images to delete files
            $stmt = $pdo->prepare("SELECT image_path FROM qc_item_images WHERE record_id = ?");
            $stmt->execute([$record_id]);
            $images = $stmt->fetchAll();
            
            foreach ($images as $img) {
                if (file_exists($img['image_path'])) unlink($img['image_path']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM qc_item_images WHERE record_id = ?");
            $stmt->execute([$record_id]);
            
            $stmt = $pdo->prepare("DELETE FROM qc_damage_items WHERE record_id = ?");
            $stmt->execute([$record_id]);
            
            $stmt = $pdo->prepare("DELETE FROM qc_damage_main WHERE record_id = ?");
            $stmt->execute([$record_id]);
            
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'message' => 'Return record deleted successfully']);
            exit;
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { 
            $pdo->rollBack(); 
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch master data for initial load
$modes = $pdo->query("SELECT mode_id, mode_name FROM qc_modes ORDER BY mode_id")->fetchAll();
$reasons = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_text")->fetchAll();
$next_reference = generateReferenceNumber($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | Assign To Return Note </title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, 'Inter', sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #1e293b;
        }
        .logo span {
            color: #dc2626;
        }
        .logo p {
            font-size: 0.65rem;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .user-info {
            text-align: right;
        }
        .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .user-role {
            font-size: 0.7rem;
            color: #64748b;
        }
        .back-btn {
            background: #dc2626;
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }
        .card-header h2 i {
            color: #dc2626;
            margin-right: 8px;
        }
        .card-body {
            padding: 24px;
        }

        .reference-box {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #dc2626;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            margin-bottom: 24px;
        }
        .reference-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #dc2626;
        }
        .reference-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            font-family: monospace;
            letter-spacing: 1px;
        }
        .reference-hint {
            font-size: 0.65rem;
            color: #64748b;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #1e293b;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .searchable-select-wrapper {
            position: relative;
        }
        .searchable-select-input {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #1e293b;
            outline: none;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .searchable-select-input:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .searchable-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-top: 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .searchable-select-dropdown.show {
            display: block;
        }
        .searchable-select-search {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            background: white;
        }
        .searchable-select-search input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .searchable-select-option {
            padding: 10px 16px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }
        .searchable-select-option:hover {
            background: #fef2f2;
        }
        .searchable-select-option.selected {
            background: #fee2e2;
            color: #dc2626;
            font-weight: 600;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-success {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #dc2626;
        }

        .item-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .item-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .item-info {
            flex: 1;
            min-width: 200px;
        }
        .item-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.85rem;
        }
        .item-code {
            font-size: 0.7rem;
            color: #64748b;
            font-family: monospace;
        }
        .item-cost {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }
        .editable-cost {
            cursor: pointer;
            color: #dc2626;
            font-weight: 600;
            text-decoration: underline;
        }
        .editable-cost:hover {
            color: #b91c1c;
        }
        .return-qty {
            width: 100px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            font-size: 0.8rem;
            text-align: center;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .table-wrapper {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .data-table th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .data-table tr:hover {
            background: #fef2f2;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            margin: 2px;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .filter-input {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.8rem;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .image-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            background: #f8fafc;
            position: relative;
        }
        .image-card img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
        }
        .image-preview {
            position: relative;
            display: inline-block;
            margin: 5px;
        }
        .image-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dc2626;
        }
        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination button:hover:not(:disabled) {
            background: #fef2f2;
            border-color: #dc2626;
        }
        .pagination button.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }
        .modal-content {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
        }
        .modal-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #dc2626;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .loading-spinner {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .loading-spinner .spinner {
            width: 40px;
            height: 40px;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.7rem;
            border-top: 1px solid #e2e8f0;
        }

        .hidden { display: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .gap-2 { gap: 8px; }
        .flex { display: flex; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>QUALITY CONTROL & RETURNS</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">
                ← Back to Dashboard
            </a>
            <div class="user-info">
                <div class="user-name"><?= $_SESSION['username'] ?? 'QC Officer' ?></div>
                <div class="user-role">Return Management</div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- CREATE RETURN SECTION -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-plus-circle"></i> Create New QC Return</h2>
            <button onclick="resetCreateForm()" class="btn-outline"><i class="fa-solid fa-undo"></i> Reset Form</button>
        </div>
        <div class="card-body">
            <div class="reference-box">
                <div class="reference-label">
                    <i class="fa-solid fa-hashtag"></i> AUTO-GENERATED REFERENCE NUMBER
                </div>
                <div class="reference-number" id="reference_display"><?= $next_reference ?></div>
                <div class="reference-hint">Format: YYYYMMDD + 5-digit sequential number</div>
            </div>
            
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label class="form-label">Select Supplier <span style="color:#dc2626;">*</span></label>
                        <div class="searchable-select-wrapper">
                            <div class="searchable-select-input" id="supplierSelectInput">
                                <span id="selectedSupplierText">-- Choose Supplier --</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="searchable-select-dropdown" id="supplierDropdown">
                                <div class="searchable-select-search">
                                    <input type="text" id="supplierSearchInput" placeholder="Search supplier by name or code..." autocomplete="off">
                                </div>
                                <div id="supplierOptionsList"><div class="searchable-select-option" style="color:#94a3b8;">Loading suppliers...</div></div>
                            </div>
                        </div>
                        <input type="hidden" id="selectedSupplierId" value="">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Search Invoice Number</label>
                        <div class="flex gap-2">
                            <input type="text" id="invoice_search" placeholder="Type invoice number to search..." class="form-input">
                            <button type="button" onclick="searchInvoices()" class="btn-secondary">
                                <i class="fa-solid fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Invoice <span style="color:#dc2626;">*</span></label>
                        <select id="invoice_select" class="form-select" disabled>
                            <option value="">-- First select supplier --</option>
                        </select>
                        <div id="invoicePagination" class="flex-between mt-2 hidden">
                            <button type="button" onclick="loadPreviousInvoicePage()" class="btn-secondary" style="padding:4px 12px;">← Previous</button>
                            <span id="invoicePageInfo" style="font-size:0.7rem;">Page 1</span>
                            <button type="button" onclick="loadNextInvoicePage()" class="btn-secondary" style="padding:4px 12px;">Next →</button>
                        </div>
                    </div>
                    
                    <div id="invoice_items_container" class="hidden mt-3">
                        <label class="form-label">Returnable Items <span style="color:#dc2626;">(Click cost to edit)</span></label>
                        <div id="invoice_items_list" style="max-height: 400px; overflow-y: auto;"></div>
                        <div id="itemsPagination" class="flex-between mt-2 hidden">
                            <button type="button" onclick="loadPreviousItemsPage()" class="btn-secondary" style="padding:4px 12px;">← Previous</button>
                            <span id="itemsPageInfo" style="font-size:0.7rem;">Page 1</span>
                            <button type="button" onclick="loadNextItemsPage()" class="btn-secondary" style="padding:4px 12px;">Next →</button>
                        </div>
                    </div>
                </div>
                
                <div>
                    <form id="returnForm">
                        <div class="grid-2" style="gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">Record Date <span style="color:#dc2626;">*</span></label>
                                <input type="date" name="record_date" id="record_date" required class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mode <span style="color:#dc2626;">*</span></label>
                                <select name="mode_id" id="mode_id" required class="form-select">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($modes as $mode): ?>
                                        <option value="<?= $mode['mode_id'] ?>"><?= htmlspecialchars($mode['mode_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid-2" style="gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">Document Number</label>
                                <input type="text" name="doc_number" id="doc_number" class="form-input" placeholder="Optional - Your document reference">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Return Reason <span style="color:#dc2626;">*</span></label>
                                <div class="searchable-select-wrapper">
                                    <div class="searchable-select-input" id="reasonSelectInput">
                                        <span id="selectedReasonText">-- Select Reason --</span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </div>
                                    <div class="searchable-select-dropdown" id="reasonDropdown">
                                        <div class="searchable-select-search">
                                            <input type="text" id="reasonSearchInput" placeholder="Search reason..." autocomplete="off">
                                        </div>
                                        <div id="reasonOptionsList"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="selectedReasonId" name="reason_id" value="">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Remarks / Notes</label>
                            <textarea name="remarks" id="remarks" class="form-textarea" placeholder="Additional notes about this return..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Added By</label>
                            <input type="text" name="added_by_user" id="added_by_user" value="QC Officer" class="form-input">
                        </div>
                        
                        <input type="hidden" name="supplier_id" id="form_supplier_id">
                        <input type="hidden" name="invoice_number" id="form_invoice_number">
                        <input type="hidden" name="selected_items" id="selected_items_input">
                        
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i> Create Return Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- IMAGE UPLOAD SECTION -->
    <div id="imageUploadSection" class="card hidden">
        <div class="card-header">
            <h2><i class="fa-solid fa-images"></i> Upload Images (Max 8 Images)</h2>
        </div>
        <div class="card-body">
            <form id="imageUploadForm" enctype="multipart/form-data">
                <input type="hidden" id="upload_record_id" name="record_id">
                <input type="hidden" name="uploaded_by" value="QC Officer">
                <div class="form-group">
                    <input type="file" name="images[]" id="images" accept="image/jpeg,image/png,image/jpg,image/gif" multiple class="form-input">
                    <small style="color:#64748b;">Max 8 images, 5MB each. JPG, PNG, GIF only.</small>
                </div>
                <div id="imagePreview" class="image-grid"></div>
                <button type="submit" class="btn-success mt-2">
                    <i class="fa-solid fa-upload"></i> Upload Images
                </button>
            </form>
        </div>
    </div>
    
    <!-- VIEW RETURNS SECTION -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-list"></i> QC Returns List</h2>
            <div>
                <button onclick="exportReturns()" class="btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</button>
                <button onclick="resetFilters()" class="btn-secondary"><i class="fa-solid fa-undo"></i> Reset</button>
            </div>
        </div>
        <div class="card-body">
            <div class="filter-row">
                <input type="text" id="filter_record_id" placeholder="Return ID" class="filter-input">
                <input type="text" id="filter_reference" placeholder="Reference No" class="filter-input">
                <input type="text" id="filter_invoice_number" placeholder="Invoice Number" class="filter-input">
                <select id="filter_supplier" class="filter-input">
                    <option value="">All Suppliers</option>
                </select>
                <select id="filter_mode" class="filter-input">
                    <option value="">All Modes</option>
                </select>
                <select id="filter_reason" class="filter-input">
                    <option value="">All Reasons</option>
                </select>
                <input type="date" id="filter_date_from" placeholder="Date From" class="filter-input">
                <input type="date" id="filter_date_to" placeholder="Date To" class="filter-input">
            </div>
            
            <div class="flex gap-2 mb-3">
                <button onclick="loadQCReturns(1)" class="btn-primary" style="width: auto; padding: 8px 20px;">
                    <i class="fa-solid fa-search"></i> Search
                </button>
                <select id="per_page_select" onchange="loadQCReturns(1)" class="filter-input" style="width: auto;">
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
            
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Reference</th><th>Date</th><th>Invoice</th><th>Supplier</th><th>Mode</th><th>Reason</th><th>Qty</th><th>Total (LKR)</th><th>Images</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="returnsTableBody">
                        <tr><td colspan="12" class="text-center" style="padding: 40px;"><div class="spinner"></div> Loading returns...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div id="tablePagination" class="pagination"></div>
        </div>
    </div>
    
    <div class="footer">
        <p>© <?= date('Y') ?> ASB Fashion - Quality Control & Returns Management System | Large Data Ready</p>
    </div>
</div>

<!-- View Return Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-eye"></i> Return Details</h3>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="viewModalContent" class="modal-body"></div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
        <div>Processing...</div>
    </div>
</div>

<script>
    let currentRecordId = null;
    let selectedReturnItems = [];
    let currentSupplierId = null;
    let suppliersData = [];
    let reasonsData = [];
    
    let currentInvoicePage = 1;
    let currentItemsPage = 1;
    let currentReturnsPage = 1;
    let totalInvoicePages = 1;
    let totalItemsPages = 1;
    let totalReturnsPages = 1;
    let currentInvoiceId = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('record_date').valueAsDate = new Date();
        loadSuppliers();
        loadModesAndReasons();
        loadQCReturns(1);
        loadFilterOptions();
        
        document.getElementById('images').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            const files = Array.from(e.target.files);
            if (files.length > 8) { showToast('Maximum 8 images allowed!', 'error'); this.value = ''; return; }
            files.forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    showToast(`File ${file.name} exceeds 5MB limit`, 'error');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    const div = document.createElement('div');
                    div.className = 'image-preview';
                    div.innerHTML = `<img src="${event.target.result}"><div class="remove-image" onclick="this.parentElement.remove()">×</div>`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });
        
        setupSearchableSupplier();
        setupSearchableReason();
        
        document.getElementById('invoice_select').addEventListener('change', function() {
            const invoiceId = this.value;
            const invoiceNumber = this.options[this.selectedIndex]?.text.split(' - ')[0] || '';
            if (invoiceId) {
                currentInvoiceId = invoiceId;
                currentItemsPage = 1;
                loadInvoiceItems(invoiceId, 1);
                document.getElementById('form_invoice_number').value = invoiceNumber;
                document.getElementById('invoice_items_container').classList.remove('hidden');
            }
        });
        
        document.getElementById('returnForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkboxes.length === 0) { showToast('Please select at least one item to return', 'error'); return; }
            
            selectedReturnItems = [];
            for (const cb of checkboxes) {
                const row = cb.closest('.item-row');
                const quantity = row.querySelector('.return-quantity').value;
                const itemCode = cb.value;
                const itemName = row.querySelector('.item-name')?.innerText || '';
                let unitCost = parseFloat(row.querySelector('.item-cost-input')?.value || row.dataset.cost || 0);
                
                if (quantity && parseInt(quantity) > 0) {
                    selectedReturnItems.push({
                        item_code: itemCode,
                        item_name: itemName,
                        quantity: parseInt(quantity),
                        unit_cost: unitCost
                    });
                }
            }
            
            if (selectedReturnItems.length === 0) { showToast('Please enter valid return quantities', 'error'); return; }
            
            let summary = 'Return Items Summary:\n\n';
            let totalValue = 0;
            selectedReturnItems.forEach(item => {
                const itemTotal = item.quantity * item.unit_cost;
                totalValue += itemTotal;
                summary += `${item.item_name}\n  Qty: ${item.quantity} × Cost: ${item.unit_cost.toFixed(2)} = ${itemTotal.toFixed(2)} LKR\n\n`;
            });
            summary += `\n─────────────────────\nTotal Return Value: ${totalValue.toFixed(2)} LKR`;
            
            if (!confirm(summary + '\n\nProceed to create return record?')) {
                return;
            }
            
            showLoading();
            document.getElementById('selected_items_input').value = JSON.stringify(selectedReturnItems);
            const fd = new FormData(this);
            const response = await fetch(window.location.pathname + '?action=create_qc_return', { method: 'POST', body: fd });
            const result = await response.json();
            hideLoading();
            
            if (result.status === 'success') {
                currentRecordId = result.record_id;
                document.getElementById('upload_record_id').value = currentRecordId;
                document.getElementById('imageUploadSection').classList.remove('hidden');
                showToast(`✅ Return created! Reference: ${result.reference_number}\nTotal: ${totalValue.toFixed(2)} LKR`, 'success');
                loadQCReturns(1);
                resetCreateForm();
                document.getElementById('record_date').valueAsDate = new Date();
            } else {
                showToast('Error: ' + result.message, 'error');
            }
        });
        
        document.getElementById('imageUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            showLoading();
            const fd = new FormData(this);
            const response = await fetch(window.location.pathname + '?action=upload_images', { method: 'POST', body: fd });
            const result = await response.json();
            hideLoading();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                document.getElementById('images').value = '';
                document.getElementById('imagePreview').innerHTML = '';
                loadQCReturns(currentReturnsPage);
            }
        });
    });
    
    function setupSearchableSupplier() {
        const selectInput = document.getElementById('supplierSelectInput');
        const dropdown = document.getElementById('supplierDropdown');
        const searchInput = document.getElementById('supplierSearchInput');
        
        selectInput.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                searchInput.focus();
                renderSupplierOptions(suppliersData);
            }
        });
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filtered = suppliersData.filter(s => 
                s.supplier_name.toLowerCase().includes(searchTerm) || 
                (s.system_id && s.system_id.toLowerCase().includes(searchTerm))
            );
            renderSupplierOptions(filtered);
        });
        
        document.addEventListener('click', function(e) {
            if (!selectInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        searchInput.addEventListener('click', e => e.stopPropagation());
    }
    
    function renderSupplierOptions(suppliers) {
        const container = document.getElementById('supplierOptionsList');
        if (!suppliers.length) {
            container.innerHTML = '<div class="searchable-select-option" style="color:#94a3b8;">No suppliers found</div>';
            return;
        }
        
        container.innerHTML = suppliers.map(supplier => `
            <div class="searchable-select-option" data-id="${supplier.supplier_id}" data-name="${escapeHtml(supplier.supplier_name)}" data-code="${supplier.system_id || ''}">
                <div style="font-weight:600;">${escapeHtml(supplier.supplier_name)}</div>
                <div style="font-size:0.7rem; color:#64748b;">ID: ${supplier.system_id || 'N/A'} | Contact: ${supplier.contact_number || 'N/A'}</div>
            </div>
        `).join('');
        
        container.querySelectorAll('.searchable-select-option').forEach(option => {
            option.addEventListener('click', function() {
                const supplierId = this.dataset.id;
                const supplierName = this.dataset.name;
                selectSupplier(supplierId, supplierName);
                document.getElementById('supplierDropdown').classList.remove('show');
            });
        });
    }
    
    function selectSupplier(supplierId, supplierName) {
        currentSupplierId = supplierId;
        document.getElementById('selectedSupplierId').value = supplierId;
        document.getElementById('selectedSupplierText').innerHTML = supplierName;
        document.getElementById('form_supplier_id').value = supplierId;
        
        currentInvoicePage = 1;
        loadInvoices(supplierId, '', 1);
        
        document.getElementById('invoice_select').innerHTML = '<option value="">-- Select Invoice --</option>';
        document.getElementById('invoice_select').disabled = false;
        document.getElementById('invoice_items_container').classList.add('hidden');
    }
    
    function setupSearchableReason() {
        const selectInput = document.getElementById('reasonSelectInput');
        const dropdown = document.getElementById('reasonDropdown');
        const searchInput = document.getElementById('reasonSearchInput');
        
        selectInput.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                searchInput.focus();
                renderReasonOptions(reasonsData);
            }
        });
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filtered = reasonsData.filter(r => 
                r.reason_text.toLowerCase().includes(searchTerm)
            );
            renderReasonOptions(filtered);
        });
        
        document.addEventListener('click', function(e) {
            if (!selectInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        searchInput.addEventListener('click', e => e.stopPropagation());
    }
    
    function renderReasonOptions(reasons) {
        const container = document.getElementById('reasonOptionsList');
        if (!reasons.length) {
            container.innerHTML = '<div class="searchable-select-option" style="color:#94a3b8;">No reasons found</div>';
            return;
        }
        
        container.innerHTML = reasons.map(reason => `
            <div class="searchable-select-option" data-id="${reason.reason_id}" data-text="${escapeHtml(reason.reason_text)}">
                ${escapeHtml(reason.reason_text)}
            </div>
        `).join('');
        
        container.querySelectorAll('.searchable-select-option').forEach(option => {
            option.addEventListener('click', function() {
                const reasonId = this.dataset.id;
                const reasonText = this.dataset.text;
                selectReason(reasonId, reasonText);
                document.getElementById('reasonDropdown').classList.remove('show');
            });
        });
    }
    
    function selectReason(reasonId, reasonText) {
        document.getElementById('selectedReasonId').value = reasonId;
        document.getElementById('selectedReasonText').innerHTML = reasonText;
    }
    
    async function loadSuppliers() {
        const response = await fetch(window.location.pathname + '?action=fetch_suppliers', { method: 'POST' });
        const result = await response.json();
        if (result.status === 'success') {
            suppliersData = result.data;
            renderSupplierOptions(suppliersData);
            
            const filterSelect = document.getElementById('filter_supplier');
            filterSelect.innerHTML = '<option value="">All Suppliers</option>';
            result.data.forEach(supplier => {
                filterSelect.innerHTML += `<option value="${supplier.supplier_id}">${escapeHtml(supplier.supplier_name)}</option>`;
            });
        }
    }
    
    async function loadModesAndReasons() {
        const reasonsResp = await fetch(window.location.pathname + '?action=get_return_reasons', { method: 'POST' });
        const reasonsResult = await reasonsResp.json();
        if (reasonsResult.status === 'success') {
            reasonsData = reasonsResult.data;
            renderReasonOptions(reasonsData);
        }
        
        const modesResp = await fetch(window.location.pathname + '?action=get_modes', { method: 'POST' });
        const modesResult = await modesResp.json();
        if (modesResult.status === 'success') {
            const filterMode = document.getElementById('filter_mode');
            filterMode.innerHTML = '<option value="">All Modes</option>';
            modesResult.data.forEach(mode => {
                filterMode.innerHTML += `<option value="${mode.mode_id}">${escapeHtml(mode.mode_name)}</option>`;
            });
        }
    }
    
    async function loadFilterOptions() {
        const reasonsResp = await fetch(window.location.pathname + '?action=get_return_reasons', { method: 'POST' });
        const reasonsResult = await reasonsResp.json();
        if (reasonsResult.status === 'success') {
            const filterReason = document.getElementById('filter_reason');
            filterReason.innerHTML = '<option value="">All Reasons</option>';
            reasonsResult.data.forEach(reason => {
                filterReason.innerHTML += `<option value="${reason.reason_id}">${escapeHtml(reason.reason_text)}</option>`;
            });
        }
    }
    
    async function searchInvoices() {
        if (!currentSupplierId) { showToast('Please select a supplier first', 'error'); return; }
        currentInvoicePage = 1;
        loadInvoices(currentSupplierId, document.getElementById('invoice_search').value, 1);
    }
    
    async function loadInvoices(supplierId, searchTerm, page) {
        const fd = new FormData();
        fd.append('supplier_id', supplierId);
        fd.append('search_invoice', searchTerm);
        fd.append('page', page);
        const response = await fetch(window.location.pathname + '?action=fetch_invoices', { method: 'POST', body: fd });
        const result = await response.json();
        const select = document.getElementById('invoice_select');
        
        if (result.status === 'success' && result.data.length > 0) {
            select.innerHTML = '<option value="">-- Select Invoice --</option>';
            result.data.forEach(inv => {
                select.innerHTML += `<option value="${inv.invoice_id}">${escapeHtml(inv.invoice_number)} - ${inv.invoice_date} (${inv.total_returns} items to return)</option>`;
            });
            select.disabled = false;
            
            if (result.pagination) {
                totalInvoicePages = result.pagination.total_pages;
                currentInvoicePage = result.pagination.current_page;
                const pageInfo = document.getElementById('invoicePageInfo');
                const paginationDiv = document.getElementById('invoicePagination');
                if (totalInvoicePages > 1) {
                    paginationDiv.classList.remove('hidden');
                    pageInfo.innerText = `Page ${currentInvoicePage} of ${totalInvoicePages}`;
                } else {
                    paginationDiv.classList.add('hidden');
                }
            }
        } else {
            select.innerHTML = '<option value="">No invoices found</option>';
            select.disabled = true;
            document.getElementById('invoicePagination').classList.add('hidden');
        }
    }
    
    function loadPreviousInvoicePage() {
        if (currentInvoicePage > 1) {
            currentInvoicePage--;
            loadInvoices(currentSupplierId, document.getElementById('invoice_search').value, currentInvoicePage);
        }
    }
    
    function loadNextInvoicePage() {
        if (currentInvoicePage < totalInvoicePages) {
            currentInvoicePage++;
            loadInvoices(currentSupplierId, document.getElementById('invoice_search').value, currentInvoicePage);
        }
    }
    
    async function loadInvoiceItems(invoiceId, page) {
        showLoading();
        const fd = new FormData();
        fd.append('invoice_id', invoiceId);
        fd.append('page', page);
        const response = await fetch(window.location.pathname + '?action=fetch_invoice_items', { method: 'POST', body: fd });
        const result = await response.json();
        hideLoading();
        
        const container = document.getElementById('invoice_items_list');
        if (result.status === 'success' && result.data.length > 0) {
            container.innerHTML = '<div style="font-size:0.7rem; color:#dc2626; margin-bottom:8px;">📦 Select items to return (Click cost to edit):</div>';
            for (const item of result.data) {
                const costResp = await fetch(window.location.pathname + '?action=get_item_cost', { method: 'POST', body: new URLSearchParams({ item_code: item.item_code }) });
                const costData = await costResp.json();
                const currentCost = costData.cost_price || item.cost_price || 0;
                const itemName = costData.item_name || item.item_name;
                
                container.innerHTML += `
                    <div class="item-row" data-item-code="${escapeHtml(item.item_code)}" data-cost="${currentCost}">
                        <input type="checkbox" class="item-checkbox" value="${escapeHtml(item.item_code)}">
                        <div class="item-info">
                            <div class="item-name">${escapeHtml(itemName)}</div>
                            <div class="item-code">📄 Code: ${escapeHtml(item.item_code)} | Available Return Qty: ${item.return_qty}</div>
                            <div class="item-cost">
                                💰 Unit Cost: <span class="editable-cost" onclick="editCost(this)" data-item-code="${escapeHtml(item.item_code)}" data-current-cost="${currentCost}">${parseFloat(currentCost).toFixed(2)} LKR</span>
                                <input type="hidden" class="item-cost-input" value="${currentCost}">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.7rem;">📦 Return Qty:</label>
                            <input type="number" class="return-quantity" value="${item.return_qty}" max="${item.return_qty}" min="0" step="1">
                        </div>
                    </div>
                `;
            }
            container.innerHTML += '<div style="font-size:0.7rem; color:#64748b; margin-top:12px; padding:8px; background:#f1f5f9; border-radius:8px;">💡 Tip: Click on the cost value to change it before submission</div>';
            
            if (result.pagination) {
                totalItemsPages = result.pagination.total_pages;
                currentItemsPage = result.pagination.current_page;
                const pageInfo = document.getElementById('itemsPageInfo');
                const paginationDiv = document.getElementById('itemsPagination');
                if (totalItemsPages > 1) {
                    paginationDiv.classList.remove('hidden');
                    pageInfo.innerText = `Page ${currentItemsPage} of ${totalItemsPages}`;
                } else {
                    paginationDiv.classList.add('hidden');
                }
            }
        } else {
            container.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">📭 No returnable items found for this invoice</div>';
            document.getElementById('itemsPagination').classList.add('hidden');
        }
    }
    
    function loadPreviousItemsPage() {
        if (currentItemsPage > 1 && currentInvoiceId) {
            currentItemsPage--;
            loadInvoiceItems(currentInvoiceId, currentItemsPage);
        }
    }
    
    function loadNextItemsPage() {
        if (currentItemsPage < totalItemsPages && currentInvoiceId) {
            currentItemsPage++;
            loadInvoiceItems(currentInvoiceId, currentItemsPage);
        }
    }
    
    async function editCost(element) {
        const itemCode = element.dataset.itemCode;
        const currentCost = element.dataset.currentCost;
        const newCost = prompt('💰 Enter new unit cost (LKR):', currentCost);
        if (newCost !== null && !isNaN(newCost) && parseFloat(newCost) >= 0) {
            const newCostValue = parseFloat(newCost).toFixed(2);
            element.textContent = newCostValue + ' LKR';
            element.dataset.currentCost = newCostValue;
            const row = element.closest('.item-row');
            row.querySelector('.item-cost-input').value = newCostValue;
            row.dataset.cost = newCostValue;
        }
    }
    
    async function loadQCReturns(page) {
        showLoading();
        const fd = new FormData();
        fd.append('page', page);
        fd.append('limit', document.getElementById('per_page_select').value);
        
        const recordId = document.getElementById('filter_record_id').value;
        const reference = document.getElementById('filter_reference').value;
        const invoiceNumber = document.getElementById('filter_invoice_number').value;
        const supplierId = document.getElementById('filter_supplier').value;
        const modeId = document.getElementById('filter_mode').value;
        const reasonId = document.getElementById('filter_reason').value;
        
        if (recordId) fd.append('record_id', recordId);
        if (reference) fd.append('reference_number', reference);
        if (invoiceNumber) fd.append('invoice_number', invoiceNumber);
        if (supplierId) fd.append('supplier_id', supplierId);
        if (modeId) fd.append('mode_id', modeId);
        if (reasonId) fd.append('reason_id', reasonId);
        if (document.getElementById('filter_date_from').value) fd.append('date_from', document.getElementById('filter_date_from').value);
        if (document.getElementById('filter_date_to').value) fd.append('date_to', document.getElementById('filter_date_to').value);
        
        const response = await fetch(window.location.pathname + '?action=fetch_qc_returns', { method: 'POST', body: fd });
        const result = await response.json();
        hideLoading();
        
        const tbody = document.getElementById('returnsTableBody');
        if (result.status === 'success' && result.data.length > 0) {
            tbody.innerHTML = result.data.map(record => `
                <tr>
                    <td style="color:#dc2626; font-weight:600;">#${record.record_id}</td>
                    <td style="font-family:monospace; font-weight:600;">${escapeHtml(record.reference_number)}</td>
                    <td>${record.record_date}</td>
                    <td>${escapeHtml(record.invoice_number)}</td>
                    <td>${escapeHtml(record.supplier_name)}</td>
                    <td>${escapeHtml(record.mode_name)}</td>
                    <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(record.reason_text)}</td>
                    <td class="text-center">${record.total_quantity || 0}</td>
                    <td class="text-right" style="font-family:monospace;">${parseFloat(record.total_cost || 0).toFixed(2)}</td>
                    <td><span class="status-badge ${(record.total_images || 0) > 0 ? 'status-completed' : 'status-pending'}">📷 ${record.total_images || 0}/8</span></td>
                    <td>${getStatusBadges(record)}</td>
                    <td>
                        <button onclick="viewReturn(${record.record_id})" style="background:#3b82f6; color:white; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; margin:2px;"><i class="fa-solid fa-eye"></i></button>
                        <button onclick="deleteReturn(${record.record_id})" style="background:#ef4444; color:white; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; margin:2px;"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
            
            if (result.pagination) {
                currentReturnsPage = result.pagination.current_page;
                totalReturnsPages = result.pagination.total_pages;
                renderPagination(currentReturnsPage, totalReturnsPages);
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center" style="padding:40px;">📋 No returns found</td></tr>';
            document.getElementById('tablePagination').innerHTML = '';
        }
    }
    
    function renderPagination(currentPage, totalPages) {
        const container = document.getElementById('tablePagination');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = `<button onclick="loadQCReturns(1)" ${currentPage === 1 ? 'disabled' : ''}>« First</button>`;
        html += `<button onclick="loadQCReturns(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹ Previous</button>`;
        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button onclick="loadQCReturns(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
        }
        
        html += `<button onclick="loadQCReturns(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next ›</button>`;
        html += `<button onclick="loadQCReturns(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>Last »</button>`;
        
        container.innerHTML = html;
    }
    
    function getStatusBadges(record) {
        let badges = '';
        badges += record.is_informed ? '<span class="status-badge status-completed">📧 Informed</span>' : '<span class="status-badge status-pending">📧</span>';
        badges += record.is_store_received ? '<span class="status-badge status-completed">🏪 Store</span>' : '<span class="status-badge status-pending">🏪</span>';
        badges += record.is_gate_cleared ? '<span class="status-badge status-completed">🚪 Gate</span>' : '<span class="status-badge status-pending">🚪</span>';
        badges += record.is_handover_complete ? '<span class="status-badge status-completed">✅ Done</span>' : '<span class="status-badge status-pending">⏳</span>';
        return badges;
    }
    
    async function viewReturn(recordId) {
        showLoading();
        const fd = new FormData();
        fd.append('record_id', recordId);
        const response = await fetch(window.location.pathname + '?action=get_qc_return_details', { method: 'POST', body: fd });
        const result = await response.json();
        hideLoading();
        
        if (result.status === 'success') {
            const totalReturnValue = result.items.reduce((sum, item) => sum + (item.quantity * item.unit_cost), 0);
            const modalContent = document.getElementById('viewModalContent');
            modalContent.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(3,1fr); gap:12px; margin-bottom:20px;">
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">📋 Return ID</div><div style="font-weight:700; color:#dc2626;">#${result.main.record_id}</div></div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">🔖 Reference</div><div style="font-weight:700; font-family:monospace;">${escapeHtml(result.main.reference_number)}</div></div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">📅 Date</div><div style="font-weight:700;">${result.main.record_date}</div></div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">🧾 Invoice</div><div style="font-weight:700;">${escapeHtml(result.main.invoice_number)}</div></div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">🏭 Supplier</div><div style="font-weight:700;">${escapeHtml(result.main.supplier_name)}</div></div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">⚙️ Mode / Reason</div><div style="font-weight:700;">${escapeHtml(result.main.mode_name)} / ${escapeHtml(result.main.reason_text)}</div></div>
                    ${result.main.doc_number ? `<div style="background:#f8fafc; padding:12px; border-radius:12px;"><div style="font-size:0.7rem; color:#64748b;">📄 Document No</div><div style="font-weight:700;">${escapeHtml(result.main.doc_number)}</div></div>` : ''}
                    ${result.main.remarks ? `<div style="background:#f8fafc; padding:12px; border-radius:12px; grid-column:span 2;"><div style="font-size:0.7rem; color:#64748b;">📝 Remarks</div><div style="font-weight:500;">${escapeHtml(result.main.remarks)}</div></div>` : ''}
                </div>
                
                <div style="margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <h4 style="font-weight:700;">📦 Returned Items</h4>
                        <button onclick="editItems(${result.main.record_id})" class="btn-secondary"><i class="fa-solid fa-pen"></i> Edit Items</button>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Item Code</th><th>Item Name</th><th>Qty</th><th>Unit Cost (LKR)</th><th>Total (LKR)</th></tr></thead>
                            <tbody>
                                ${result.items.map(item => `
                                    <tr>
                                        <td class="font-mono">${escapeHtml(item.item_code)}</td>
                                        <td>${escapeHtml(item.item_name)}</td>
                                        <td class="text-center font-semibold">${item.quantity}</td>
                                        <td class="text-right">${parseFloat(item.unit_cost).toFixed(2)}</td>
                                        <td class="text-right" style="color:#dc2626;">${parseFloat(item.total_cost).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                                <tr style="background:#f8fafc;">
                                    <td colspan="4" class="text-right"><strong>Total Return Value:</strong></td>
                                    <td class="text-right"><strong style="font-size:1.1rem; color:#dc2626;">${totalReturnValue.toFixed(2)} LKR</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div style="margin-bottom:20px;">
                    <h4 style="font-weight:700; margin-bottom:12px;">🖼️ Images (${result.images.length}/8)</h4>
                    <div class="image-grid">
                        ${result.images.map(img => `<div class="image-card"><img src="${img.image_path}"><a href="${img.image_path}" target="_blank" style="font-size:0.7rem; color:#3b82f6;">🔍 View</a></div>`).join('')}
                        ${result.images.length === 0 ? '<div style="color:#64748b;">No images uploaded</div>' : ''}
                    </div>
                </div>
                
                <div>
                    <h4 style="font-weight:700; margin-bottom:12px;">✅ Status Updates</h4>
                    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                        ${getStatusUpdateButtons(result.main)}
                    </div>
                </div>
            `;
            document.getElementById('viewModal').style.display = 'block';
        }
    }
    
    function getStatusUpdateButtons(record) {
        return `
            <div style="background:#f8fafc; padding:12px; border-radius:12px;">
                <div style="font-size:0.7rem; color:#64748b;">📧 Supplier Informed</div>
                <div class="flex-between mt-2">
                    <span class="${record.is_informed ? 'status-completed' : 'status-pending'} status-badge">${record.is_informed ? '✓ Informed' : '⏳ Pending'}</span>
                    ${!record.is_informed ? `<button onclick="updateStatus(${record.record_id}, 'is_informed', 1)" class="btn-secondary">Mark</button>` : ''}
                </div>
                ${record.informed_by_user ? `<div style="font-size:0.7rem; color:#64748b; margin-top:8px;">👤 By: ${record.informed_by_user}<br>⏱️ ${record.informed_datetime}</div>` : ''}
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:12px;">
                <div style="font-size:0.7rem; color:#64748b;">🏪 Store Received</div>
                <div class="flex-between mt-2">
                    <span class="${record.is_store_received ? 'status-completed' : 'status-pending'} status-badge">${record.is_store_received ? '✓ Received' : '⏳ Pending'}</span>
                    ${!record.is_store_received ? `<button onclick="updateStatus(${record.record_id}, 'is_store_received', 1)" class="btn-secondary">Mark</button>` : ''}
                </div>
                ${record.store_user ? `<div style="font-size:0.7rem; color:#64748b; margin-top:8px;">👤 By: ${record.store_user}<br>⏱️ ${record.store_datetime}</div>` : ''}
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:12px;">
                <div style="font-size:0.7rem; color:#64748b;">🚪 Gate Cleared</div>
                <div class="flex-between mt-2">
                    <span class="${record.is_gate_cleared ? 'status-completed' : 'status-pending'} status-badge">${record.is_gate_cleared ? '✓ Cleared' : '⏳ Pending'}</span>
                    ${!record.is_gate_cleared ? `<button onclick="updateStatus(${record.record_id}, 'is_gate_cleared', 1)" class="btn-secondary">Mark</button>` : ''}
                </div>
                ${record.gate_user ? `<div style="font-size:0.7rem; color:#64748b; margin-top:8px;">👤 By: ${record.gate_user}<br>⏱️ ${record.gate_datetime}</div>` : ''}
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:12px;">
                <div style="font-size:0.7rem; color:#64748b;">✅ Handover Complete</div>
                <div class="flex-between mt-2">
                    <span class="${record.is_handover_complete ? 'status-completed' : 'status-pending'} status-badge">${record.is_handover_complete ? '✓ Complete' : '⏳ Pending'}</span>
                    ${!record.is_handover_complete ? `<button onclick="updateStatus(${record.record_id}, 'is_handover_complete', 1)" class="btn-secondary">Mark</button>` : ''}
                </div>
                ${record.handover_user ? `<div style="font-size:0.7rem; color:#64748b; margin-top:8px;">👤 By: ${record.handover_user}<br>⏱️ ${record.handover_datetime}</div>` : ''}
            </div>
        `;
    }
    
    async function editItems(recordId) {
        const fd = new FormData();
        fd.append('record_id', recordId);
        const response = await fetch(window.location.pathname + '?action=get_qc_return_details', { method: 'POST', body: fd });
        const result = await response.json();
        if (result.status === 'success') {
            let itemsHtml = '<div style="max-height:400px; overflow-y:auto;">';
            for (const item of result.items) {
                itemsHtml += `
                    <div style="background:#f8fafc; padding:12px; border-radius:12px; margin-bottom:8px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <div style="flex:1; min-width:150px;">
                            <div style="font-weight:700;">${escapeHtml(item.item_name)}</div>
                            <div style="font-size:0.7rem; color:#64748b;">${escapeHtml(item.item_code)}</div>
                        </div>
                        <div><label style="font-size:0.7rem;">📦 Qty:</label><br><input type="number" id="qty_${item.item_id}" value="${item.quantity}" style="width:80px; padding:6px; border:1px solid #e2e8f0; border-radius:8px;" min="0"></div>
                        <div><label style="font-size:0.7rem;">💰 Cost:</label><br><input type="number" id="cost_${item.item_id}" value="${item.unit_cost}" step="0.01" style="width:100px; padding:6px; border:1px solid #e2e8f0; border-radius:8px;" min="0"></div>
                    </div>
                `;
            }
            itemsHtml += '</div><div style="display:flex; gap:12px; margin-top:16px; flex-wrap:wrap;"><button onclick="saveItemEdits(' + recordId + ')" class="btn-primary" style="width:auto;">💾 Save Changes</button><button onclick="closeViewModal(); viewReturn(' + recordId + ')" class="btn-secondary">❌ Cancel</button></div>';
            
            const modalContent = document.getElementById('viewModalContent');
            modalContent.innerHTML = `<h3 style="font-size:1.1rem; font-weight:700; margin-bottom:16px;">✏️ Edit Return Items</h3>${itemsHtml}`;
        }
    }
    
    async function saveItemEdits(recordId) {
        showLoading();
        const updatedItems = [];
        const items = await fetch(window.location.pathname + '?action=get_qc_return_details', { method: 'POST', body: new URLSearchParams({ record_id: recordId }) }).then(r => r.json());
        for (const item of items.items) {
            const newQty = document.getElementById(`qty_${item.item_id}`)?.value;
            const newCost = document.getElementById(`cost_${item.item_id}`)?.value;
            if (newQty !== undefined && newCost !== undefined) {
                updatedItems.push({ item_id: item.item_id, quantity: parseInt(newQty) || 0, unit_cost: parseFloat(newCost) || 0 });
            }
        }
        const fd = new FormData();
        fd.append('record_id', recordId);
        fd.append('updated_items', JSON.stringify(updatedItems));
        const response = await fetch(window.location.pathname + '?action=update_return_items', { method: 'POST', body: fd });
        const result = await response.json();
        hideLoading();
        showToast(result.message, result.status === 'success' ? 'success' : 'error');
        viewReturn(recordId);
        loadQCReturns(currentReturnsPage);
    }
    
    async function updateStatus(recordId, field, value) {
        const fd = new FormData();
        fd.append('record_id', recordId);
        fd.append('field', field);
        fd.append('value', value);
        fd.append('user', 'QC Officer');
        const response = await fetch(window.location.pathname + '?action=update_return_status', { method: 'POST', body: fd });
        const result = await response.json();
        if (result.status === 'success') { 
            viewReturn(recordId); 
            loadQCReturns(currentReturnsPage);
            showToast('Status updated successfully', 'success');
        } else {
            showToast('Error: ' + result.message, 'error');
        }
    }
    
    async function deleteReturn(recordId) {
        if (!confirm('⚠️ Are you sure you want to delete this return record?\nThis action cannot be undone and will also delete all associated items and images.')) {
            return;
        }
        
        showLoading();
        const fd = new FormData();
        fd.append('record_id', recordId);
        const response = await fetch(window.location.pathname + '?action=delete_return', { method: 'POST', body: fd });
        const result = await response.json();
        hideLoading();
        
        showToast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') {
            loadQCReturns(1);
            closeViewModal();
        }
    }
    
    async function exportReturns() {
        showLoading();
        const fd = new FormData();
        const supplierId = document.getElementById('filter_supplier').value;
        const dateFrom = document.getElementById('filter_date_from').value;
        const dateTo = document.getElementById('filter_date_to').value;
        
        if (supplierId) fd.append('supplier_id', supplierId);
        if (dateFrom) fd.append('date_from', dateFrom);
        if (dateTo) fd.append('date_to', dateTo);
        
        const response = await fetch(window.location.pathname + '?action=export_returns', { method: 'POST', body: fd });
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `qc_returns_export_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        hideLoading();
        showToast('Export completed', 'success');
    }
    
    function resetFilters() {
        ['filter_record_id', 'filter_reference', 'filter_invoice_number', 'filter_date_from', 'filter_date_to'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('filter_supplier').value = '';
        document.getElementById('filter_mode').value = '';
        document.getElementById('filter_reason').value = '';
        loadQCReturns(1);
    }
    
    function resetCreateForm() {
        document.getElementById('selectedSupplierId').value = '';
        document.getElementById('selectedSupplierText').innerHTML = '-- Choose Supplier --';
        document.getElementById('selectedReasonId').value = '';
        document.getElementById('selectedReasonText').innerHTML = '-- Select Reason --';
        document.getElementById('form_supplier_id').value = '';
        document.getElementById('form_invoice_number').value = '';
        document.getElementById('doc_number').value = '';
        document.getElementById('remarks').value = '';
        document.getElementById('invoice_select').innerHTML = '<option value="">-- First select supplier --</option>';
        document.getElementById('invoice_select').disabled = true;
        document.getElementById('invoice_items_container').classList.add('hidden');
        document.getElementById('imageUploadSection').classList.add('hidden');
        currentSupplierId = null;
        currentInvoiceId = null;
    }
    
    function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
    
    function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }
</script>
</body>
</html>