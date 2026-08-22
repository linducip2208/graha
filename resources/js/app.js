const themeToggle=document.querySelector('#theme-toggle');const syncThemeIcon=()=>{if(themeToggle)themeToggle.textContent=document.documentElement.classList.contains('dark')?'☀️':'🌙'};syncThemeIcon();themeToggle?.addEventListener('click',()=>{const dark=document.documentElement.classList.toggle('dark');localStorage.setItem('theme',dark?'dark':'light');syncThemeIcon();if(window.Chart){Chart.defaults.color=dark?'#94a3b8':'#334155';Chart.defaults.borderColor=dark?'#22304d':'#e2e8f0';Object.values(Chart.instances).forEach(chart=>chart.update())}});
const sidebar=document.querySelector('#admin-sidebar');const overlay=document.querySelector('#sidebar-overlay');document.querySelectorAll('[data-sidebar-open]').forEach(b=>b.addEventListener('click',()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden')}));document.querySelectorAll('[data-sidebar-close]').forEach(b=>b.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden')}));
const toastRoot=document.querySelector('#toast-root');const showToast=(message,tone)=>{if(!message||!toastRoot)return;const el=document.createElement('div');el.className=`toast-item ${tone==='error'?'bg-red-600':'bg-slate-900'}`;el.innerHTML=`<span>${tone==='error'?'⚠️':'✅'}</span><span></span>`;el.lastChild.textContent=message;toastRoot.append(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),400)},5000)};
showToast(document.body.dataset.flash,'success');showToast(document.body.dataset.flashError,'error');
const uiCopy={
'Document Control':['Pengendalian Dokumen dan Riwayat Revisi','Daftarkan dokumen, pertahankan versi lama, dan pastikan pengguna selalu memakai revisi yang berlaku.'],
'Inventory & Gudang':['Persediaan Material dan Operasi Gudang','Kelola material, lokasi penyimpanan, saldo, serta setiap penerimaan dan pengeluaran yang dapat ditelusuri.'],
'Master Item & Gudang':['Tambah Material, Gudang, dan Lokasi Penyimpanan','Membuat master material beserta gudang dan bin awal sebelum transaksi stok dilakukan.'],
'Posting Movement':['Catat Penerimaan atau Pengeluaran Stok','Membukukan perubahan stok ke ledger immutable; pilih jenis transaksi sesuai dokumen sumber.'],
'Fixed Asset & Depresiasi':['Aset Tetap dan Penyusutan','Kelola aset perusahaan dan bukukan penyusutan periodik secara otomatis ke jurnal.'],
'Register Aset':['Daftarkan Aset Tetap','Catat harga perolehan, nilai residu, tanggal mulai penyusutan, dan umur manfaat aset.'],
'Mapping Depresiasi':['Tentukan Akun Jurnal Penyusutan','Pilih akun beban penyusutan dan akumulasi penyusutan tanpa hard-code nomor akun.'],
'Workflow Baru':['Buat Alur Persetujuan Baru','Tentukan jenis dokumen, urutan pemeriksa, batas nilai, SLA, dan aturan keputusan.'],
'Delegation':['Delegasikan Kewenangan Sementara','Alihkan tugas persetujuan untuk periode tertentu dengan alasan dan jejak audit.'],
'My Approvals / Pending':['Dokumen yang Menunggu Keputusan Saya','Daftar pekerjaan persetujuan yang harus diperiksa sebelum melewati SLA.'],
'Workflow Configuration':['Konfigurasi Tahapan Persetujuan','Atur siapa memeriksa, urutan keputusan, quorum, dan tenggat setiap tahap.'],
'Chart of Accounts':['Daftar Akun Akuntansi','Struktur akun yang dipakai seluruh transaksi ERP untuk menghasilkan jurnal.'],
'General Ledger':['Buku Besar dan Jurnal','Tinjau jurnal posted yang seimbang dan tidak dapat diubah secara diam-diam.'],
'Input Statement':['Catat Baris Rekening Koran','Masukkan transaksi dari rekening koran untuk dicocokkan dengan penerimaan atau pembayaran.'],
'Period Closing':['Tutup Periode Akuntansi','Menutup periode setelah approval selesai dan seluruh transaksi bank telah direkonsiliasi.'],
'Bill of Material':['Susunan Bahan Produk (BOM)','Tentukan material dan kuantitas standar untuk menghasilkan satu unit produk.'],
'Production Order':['Perintah Produksi','Rencanakan jumlah yang dibuat, gudang output, dan BOM yang wajib digunakan.'],
'Production Orders':['Daftar dan Status Perintah Produksi','Pantau rencana, material yang dipakai, hasil aktual, biaya, dan status penyelesaian produksi.'],
'Equipment Register':['Daftarkan Mesin dan Peralatan','Catat kepemilikan, kategori, hour meter, dan target konsumsi bahan bakar.'],
'Vendor':['Tambah Vendor','Daftarkan pemasok yang akan digunakan pada transaksi pembelian.'],
'Draft Purchase Order':['Buat Draft Pesanan Pembelian (PO)','Siapkan pesanan pemasok sebelum dikirim melalui proses persetujuan.'],
'Purchase Orders':['Daftar dan Status Pesanan Pembelian','Pantau approval, penerimaan barang, invoice vendor, dan revisi setiap PO.'],
'Draft Progress Billing':['Buat Draft Tagihan Progres','Hitung nilai bruto, retensi, pemulihan uang muka, dan piutang bersih sebelum approval.'],
'Release Retensi':['Ajukan Pencairan Retensi','Reklasifikasikan piutang retensi menjadi piutang pelanggan setelah syarat kontrak terpenuhi.'],
'Goods Receipt \uFF0B Inventory/GRNI':['Barang Diterima: Persediaan / Utang Sementara (GRNI)','Debit persediaan dan kredit GRNI ketika barang diterima tetapi invoice vendor belum dibukukan.'],
'Matched Invoice \uFF0B GRNI/AP':['Invoice Cocok: Utang Sementara / Utang Vendor','Pindahkan GRNI menjadi utang vendor hanya setelah PO, penerimaan, dan invoice cocok.'],
'Provider Configuration':['Konfigurasi Penyedia Tanda Tangan Eksternal','Masukkan penyedia, endpoint, dan kredensial secara dinamis; secret disimpan terenkripsi.'],
'Document Versions':['Versi Dokumen yang Siap Ditandatangani','Pilih revisi dokumen yang benar; versi bertanda tangan dikunci dan tidak dapat diganti diam-diam.'],
'Risk & Opportunity':['Catat Risiko atau Peluang','Nilai kemungkinan dan dampak, tetapkan pemilik, lalu pantau tindakan pengendalian.'],
'Nonconformity (NCR)':['Catat Ketidaksesuaian (NCR)','Rekam hasil yang tidak memenuhi persyaratan agar containment dan tindakan korektif dapat dikendalikan.']};
document.querySelectorAll('h1,h2').forEach((heading)=>{const key=heading.textContent.trim();const copy=uiCopy[key];if(!copy)return;heading.textContent=copy[0];if(copy[1]&&!heading.nextElementSibling?.classList.contains('ui-purpose')){const purpose=document.createElement('p');purpose.className='ui-purpose mt-1 text-sm text-slate-500';purpose.textContent=copy[1];heading.after(purpose)}});
document.querySelectorAll('main div').forEach((workspace)=>{const forms=[...workspace.children].filter((element)=>element.tagName==='FORM'&&!element.closest('.nav-details'));if(forms.length<2)return;workspace.classList.add('workspace-tools');const toolbar=document.createElement('div');toolbar.className='workspace-toolbar no-print';forms.forEach((form,index)=>{form.classList.add('workspace-tool-panel');form.hidden=true;const button=document.createElement('button');button.type='button';button.className='workspace-tool-button';button.textContent=form.querySelector('h2')?.textContent?.trim()||form.querySelector('button')?.textContent?.trim()||`Aksi ${index+1}`;button.addEventListener('click',()=>{const willOpen=form.hidden;forms.forEach((item)=>{item.hidden=true});toolbar.querySelectorAll('button').forEach((item)=>item.classList.remove('active'));if(willOpen){form.hidden=false;button.classList.add('active');form.scrollIntoView({behavior:'smooth',block:'nearest'})}});toolbar.append(button)});toolbar.querySelector('button')?.click();workspace.before(toolbar)});
