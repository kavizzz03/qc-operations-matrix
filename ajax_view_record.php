<?php
require 'db.php';
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT m.*, s.supplier_name, s.system_id as sid, s.contact_number, s.email as supplier_email, s.address 
                       FROM qc_damage_main m JOIN suppliers s ON m.supplier_id = s.supplier_id WHERE m.record_id = ?");
$stmt->execute([$id]);
$main = $stmt->fetch();

$items = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
$items->execute([$id]);
$items_data = $items->fetchAll();

$images = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ?");
$images->execute([$id]);
$images_data = $images->fetchAll();
?>

<input type="hidden" id="temp_ref" value="<?= $main['reference_number'] ?>">

<div class="grid grid-cols-3 gap-6 mb-10">
    <div class="bg-slate-900 p-8 rounded-[2rem] border border-slate-800 shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-10 font-black text-6xl italic">VENDOR</div>
        <label class="text-[10px] text-red-600 uppercase font-black tracking-widest block mb-4">Supplier Information</label>
        <h4 class="text-white font-black text-2xl uppercase"><?= $main['supplier_name'] ?></h4>
        <div class="mt-4 space-y-1 text-xs text-slate-400 font-medium">
            <p>SYSTEM ID: <span class="text-white font-mono"><?= $main['sid'] ?></span></p>
            <p>CONTACT: <span class="text-white"><?= $main['contact_number'] ?></span></p>
            <p>EMAIL: <span class="text-white lowercase"><?= $main['supplier_email'] ?></span></p>
            <p class="mt-2 text-[10px] uppercase text-slate-600"><?= $main['address'] ?></p>
        </div>
    </div>

    <div class="bg-slate-900 p-8 rounded-[2rem] border border-slate-800 shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-10 font-black text-6xl italic">TRACE</div>
        <label class="text-[10px] text-red-600 uppercase font-black tracking-widest block mb-4">Audit Metadata</label>
        <h4 class="text-white font-black text-2xl uppercase">#<?= $main['invoice_number'] ?></h4>
        <div class="mt-4 space-y-1 text-xs text-slate-400 font-medium">
            <p>REFERENCE: <span class="text-white"><?= $main['reference_number'] ?></span></p>
            <p>AUDITOR: <span class="text-white uppercase"><?= $main['added_by_user'] ?></span></p>
            <p>RECORDED: <span class="text-white"><?= $main['added_time'] ?></span></p>
        </div>
    </div>

    <div class="bg-slate-900 p-8 rounded-[2rem] border border-slate-800 shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-10 font-black text-6xl italic text-red-600">STATUS</div>
        <label class="text-[10px] text-red-600 uppercase font-black tracking-widest block mb-4">Risk Flag</label>
        <div class="flex flex-col gap-2">
            <span class="px-4 py-2 bg-yellow-600/20 text-yellow-500 rounded-xl border border-yellow-600/30 font-black text-xs text-center uppercase tracking-widest">
                High Risk Returns
            </span>
            <p class="text-[10px] text-slate-500 text-center leading-relaxed">This record has been flagged for manual verification by the senior QC supervisor based on total quantity.</p>
        </div>
    </div>
</div>

<!-- Rest of the code for items table and images gallery remains same as previous ajax_view_record.php -->