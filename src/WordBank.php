<?php

namespace TypingBattle;

class WordBank
{
    /**
     * Bank kosakata 1000+ kata bahasa Indonesia.
     * Digunakan untuk menghasilkan teks acak pada setiap pertandingan.
     */
    private static $words = [
        // ── Kata Kerja ──────────────────────────────────────
        'menulis', 'membaca', 'berlari', 'berjalan', 'berenang', 'memasak', 'belajar', 'bekerja',
        'bermain', 'menyanyi', 'menari', 'melompat', 'memanjat', 'menggambar', 'mewarnai', 'menjahit',
        'memotong', 'menggoreng', 'merebus', 'memanggang', 'mencuci', 'menyapu', 'mengepel', 'menyiram',
        'menanam', 'memanen', 'menerbangkan', 'mengemudi', 'mengendarai', 'menyelam', 'memancing',
        'berbicara', 'mendengar', 'melihat', 'merasa', 'menyentuh', 'mencium', 'memeluk', 'mencari',
        'menemukan', 'membuat', 'membangun', 'merancang', 'mendesain', 'mengajar', 'mendidik', 'melatih',
        'menolong', 'membantu', 'melindungi', 'menjaga', 'merawat', 'mengobati', 'menyembuhkan',
        'membuka', 'menutup', 'mengunci', 'mengirim', 'menerima', 'mengambil', 'memberikan', 'meminjam',
        'mengembalikan', 'menjual', 'membeli', 'memesan', 'mengantarkan', 'menyimpan', 'membuang',
        'mengangkat', 'menurunkan', 'mendorong', 'menarik', 'melempar', 'menangkap', 'memukul',
        'menendang', 'mengayuh', 'memutar', 'menggeser', 'meletakkan', 'menggantung', 'melipat',
        'menggunting', 'menempel', 'menuangkan', 'mengaduk', 'mencampur', 'menyaring', 'menimbang',
        'mengukur', 'menghitung', 'menjumlah', 'mengurangi', 'mengalikan', 'membagi', 'menyelesaikan',
        'memecahkan', 'memikirkan', 'menganalisis', 'mengevaluasi', 'menilai', 'memutuskan',
        'memilih', 'menentukan', 'merencanakan', 'mengatur', 'mengelola', 'memimpin', 'mengawasi',
        'memeriksa', 'menguji', 'membuktikan', 'menjelaskan', 'menceritakan', 'menguraikan',
        'menyampaikan', 'mengumumkan', 'menyiarkan', 'merekam', 'memotret', 'merekomendasikan',
        'menyarankan', 'mengusulkan', 'mengajukan', 'mendaftarkan', 'mencatatkan', 'melaporkan',
        'mengetik', 'mencetak', 'menyalin', 'mengedit', 'merevisi', 'memperbaiki', 'memperbarui',
        'mengunduh', 'mengunggah', 'mengirimkan', 'membagikan', 'menyebarkan', 'memublikasikan',
        'menerbitkan', 'meluncurkan', 'memperkenalkan', 'mempromosikan', 'memasarkan', 'mengiklankan',

        // ── Kata Benda ──────────────────────────────────────
        'rumah', 'gedung', 'sekolah', 'kantor', 'toko', 'pasar', 'jalan', 'jembatan', 'stasiun',
        'bandara', 'pelabuhan', 'terminal', 'halaman', 'taman', 'lapangan', 'stadion', 'masjid',
        'gereja', 'kuil', 'perpustakaan', 'museum', 'galeri', 'bioskop', 'teater', 'restoran',
        'kafe', 'warung', 'hotel', 'penginapan', 'apartemen', 'asrama', 'gudang', 'pabrik',
        'bengkel', 'laboratorium', 'klinik', 'rumahsakit', 'apotek', 'salon', 'laundry',
        'komputer', 'laptop', 'telepon', 'tablet', 'kamera', 'printer', 'monitor', 'keyboard',
        'mouse', 'headset', 'speaker', 'mikrofon', 'proyektor', 'televisi', 'radio', 'kulkas',
        'mesin', 'robot', 'drone', 'satelit', 'roket', 'pesawat', 'helikopter', 'kapal',
        'perahu', 'mobil', 'motor', 'sepeda', 'kereta', 'bus', 'truk', 'ambulans', 'taksi',
        'buku', 'majalah', 'koran', 'novel', 'komik', 'jurnal', 'ensiklopedia', 'kamus',
        'peta', 'atlas', 'grafik', 'tabel', 'diagram', 'poster', 'spanduk', 'brosur',
        'kertas', 'pensil', 'pulpen', 'penghapus', 'penggaris', 'jangka', 'kalkulator', 'papan',
        'meja', 'kursi', 'lemari', 'rak', 'laci', 'pintu', 'jendela', 'dinding', 'lantai',
        'atap', 'tangga', 'pagar', 'gerbang', 'pilar', 'fondasi', 'balkon', 'teras', 'garasi',
        'dapur', 'kamar', 'ruangan', 'lorong', 'aula', 'panggung', 'podium', 'mimbar',
        'air', 'udara', 'tanah', 'api', 'cahaya', 'bayangan', 'suara', 'angin', 'hujan',
        'salju', 'embun', 'kabut', 'awan', 'pelangi', 'petir', 'guntur', 'badai', 'tsunami',
        'gunung', 'bukit', 'lembah', 'dataran', 'padang', 'hutan', 'rimba', 'sungai', 'danau',
        'laut', 'samudra', 'pantai', 'pulau', 'teluk', 'tanjung', 'selat', 'muara', 'delta',
        'pohon', 'bunga', 'daun', 'akar', 'batang', 'ranting', 'biji', 'buah', 'sayur',
        'beras', 'jagung', 'gandum', 'kedelai', 'kacang', 'singkong', 'kentang', 'wortel',
        'tomat', 'cabai', 'bawang', 'jahe', 'kunyit', 'lengkuas', 'serai', 'kemangi',
        'mangga', 'apel', 'jeruk', 'pisang', 'anggur', 'semangka', 'melon', 'pepaya',
        'nanas', 'durian', 'rambutan', 'kelapa', 'jambu', 'salak', 'alpukat', 'manggis',
        'kucing', 'anjing', 'kelinci', 'hamster', 'burung', 'ikan', 'kura-kura', 'ular',
        'kadal', 'katak', 'kupu-kupu', 'lebah', 'semut', 'nyamuk', 'lalat', 'capung',
        'kuda', 'sapi', 'kambing', 'domba', 'ayam', 'bebek', 'angsa', 'kerbau', 'babi',
        'gajah', 'harimau', 'singa', 'beruang', 'serigala', 'rubah', 'rusa', 'jerapah',
        'badak', 'zebra', 'monyet', 'gorila', 'panda', 'koala', 'kanguru', 'lumba-lumba',
        'paus', 'hiu', 'gurita', 'cumi-cumi', 'kepiting', 'udang', 'kerang', 'ubur-ubur',

        // ── Kata Sifat ──────────────────────────────────────
        'besar', 'kecil', 'tinggi', 'rendah', 'panjang', 'pendek', 'lebar', 'sempit',
        'tebal', 'tipis', 'berat', 'ringan', 'keras', 'lembut', 'kasar', 'halus',
        'panas', 'dingin', 'hangat', 'sejuk', 'basah', 'kering', 'terang', 'gelap',
        'cerah', 'mendung', 'jernih', 'keruh', 'bersih', 'kotor', 'rapi', 'berantakan',
        'cepat', 'lambat', 'rajin', 'malas', 'pintar', 'bodoh', 'kuat', 'lemah',
        'sehat', 'sakit', 'muda', 'tua', 'baru', 'lama', 'segar', 'basi',
        'manis', 'pahit', 'asin', 'asam', 'pedas', 'gurih', 'tawar', 'hambar',
        'indah', 'cantik', 'tampan', 'jelek', 'bagus', 'buruk', 'baik', 'jahat',
        'senang', 'sedih', 'gembira', 'marah', 'takut', 'berani', 'malu', 'bangga',
        'tenang', 'gelisah', 'cemas', 'panik', 'sabar', 'kesal', 'puas', 'kecewa',
        'yakin', 'ragu', 'percaya', 'curiga', 'jujur', 'bohong', 'setia', 'khianat',
        'ramah', 'sombong', 'rendahhati', 'angkuh', 'sopan', 'kasar', 'lembut', 'garang',
        'murah', 'mahal', 'gratis', 'langka', 'umum', 'khusus', 'istimewa', 'biasa',
        'modern', 'klasik', 'antik', 'tradisional', 'populer', 'viral', 'eksotis', 'unik',
        'aman', 'berbahaya', 'nyaman', 'sesak', 'luas', 'lengkap', 'kosong', 'penuh',

        // ── Kata Keterangan ─────────────────────────────────
        'sangat', 'cukup', 'agak', 'sedikit', 'banyak', 'selalu', 'sering', 'kadang',
        'jarang', 'tidak', 'belum', 'sudah', 'sedang', 'akan', 'pernah', 'hampir',
        'segera', 'langsung', 'perlahan', 'pelan', 'diam-diam', 'terang-terangan',
        'sekarang', 'kemarin', 'besok', 'lusa', 'nanti', 'tadi', 'dahulu', 'kelak',
        'siang', 'malam', 'pagi', 'sore', 'subuh', 'dini', 'tengah', 'sepanjang',

        // ── Teknologi & Komputer ────────────────────────────
        'internet', 'website', 'aplikasi', 'software', 'hardware', 'database', 'server',
        'jaringan', 'firewall', 'enkripsi', 'algoritma', 'variabel', 'fungsi', 'program',
        'coding', 'debugging', 'framework', 'library', 'modul', 'plugin', 'template',
        'frontend', 'backend', 'fullstack', 'cloud', 'hosting', 'domain', 'bandwidth',
        'download', 'upload', 'streaming', 'cache', 'cookie', 'token', 'password',
        'username', 'login', 'logout', 'register', 'profil', 'akun', 'email',
        'notifikasi', 'pesan', 'chatting', 'video', 'audio', 'gambar', 'file',
        'folder', 'backup', 'update', 'install', 'uninstall', 'konfigurasi', 'setting',
        'resolusi', 'piksel', 'font', 'warna', 'desain', 'layout', 'responsif',
        'animasi', 'transisi', 'efek', 'filter', 'rendering', 'kompilasi', 'eksekusi',
        'binary', 'boolean', 'integer', 'string', 'array', 'object', 'class',
        'method', 'constructor', 'interface', 'abstract', 'static', 'public', 'private',

        // ── Pendidikan & Ilmu ────────────────────────────────
        'matematika', 'fisika', 'kimia', 'biologi', 'geografi', 'sejarah', 'ekonomi',
        'sosiologi', 'psikologi', 'filsafat', 'sastra', 'seni', 'musik', 'olahraga',
        'penelitian', 'eksperimen', 'hipotesis', 'teori', 'konsep', 'definisi', 'rumus',
        'persamaan', 'variabel', 'konstanta', 'koefisien', 'grafik', 'statistik', 'data',
        'informasi', 'pengetahuan', 'wawasan', 'keahlian', 'keterampilan', 'kemampuan',
        'kreativitas', 'inovasi', 'penemuan', 'teknologi', 'sains', 'riset', 'survei',
        'kurikulum', 'silabus', 'materi', 'pelajaran', 'ujian', 'tugas', 'proyek',
        'presentasi', 'diskusi', 'seminar', 'konferensi', 'workshop', 'pelatihan',
        'sertifikat', 'diploma', 'gelar', 'sarjana', 'magister', 'doktor', 'profesor',
        'dosen', 'guru', 'murid', 'siswa', 'mahasiswa', 'alumni', 'rektor', 'dekan',

        // ── Profesi & Pekerjaan ─────────────────────────────
        'dokter', 'perawat', 'apoteker', 'insinyur', 'arsitek', 'programmer', 'desainer',
        'akuntan', 'pengacara', 'hakim', 'jaksa', 'polisi', 'tentara', 'pilot', 'nahkoda',
        'petani', 'nelayan', 'pedagang', 'pengusaha', 'manajer', 'direktur', 'sekretaris',
        'resepsionis', 'kasir', 'pelayan', 'koki', 'barista', 'sopir', 'mekanik',
        'tukang', 'montir', 'teknisi', 'operator', 'analis', 'konsultan', 'auditor',
        'jurnalis', 'wartawan', 'editor', 'penulis', 'penerjemah', 'fotografer', 'videografer',
        'musisi', 'penyanyi', 'aktor', 'aktris', 'sutradara', 'produser', 'animator',
        'seniman', 'pemahat', 'pelukis', 'ilustrator', 'kurator', 'kritikus', 'komentator',
        'atlet', 'pelatih', 'wasit', 'promotor', 'agen', 'broker', 'distributor',
        'importir', 'eksportir', 'investor', 'bankir', 'notaris', 'diplomat', 'duta',

        // ── Makanan & Minuman ────────────────────────────────
        'nasi', 'mie', 'roti', 'bubur', 'sup', 'soto', 'bakso', 'sate',
        'rendang', 'gulai', 'rawon', 'pecel', 'gado-gado', 'rujak', 'siomay', 'batagor',
        'tempe', 'tahu', 'telur', 'daging', 'ayam', 'sosis', 'nugget', 'kerupuk',
        'sambal', 'kecap', 'saus', 'garam', 'gula', 'merica', 'cuka', 'minyak',
        'mentega', 'keju', 'susu', 'yogurt', 'es', 'jus', 'teh', 'kopi',
        'cokelat', 'sirup', 'madu', 'selai', 'biskuit', 'kue', 'puding', 'dodol',

        // ── Warna ────────────────────────────────────────────
        'merah', 'biru', 'hijau', 'kuning', 'oranye', 'ungu', 'putih', 'hitam',
        'abu-abu', 'cokelat', 'pink', 'emas', 'perak', 'krem', 'maroon', 'toska',

        // ── Angka & Waktu ────────────────────────────────────
        'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan',
        'sembilan', 'sepuluh', 'sebelas', 'duabelas', 'seratus', 'seribu', 'sejuta',
        'detik', 'menit', 'jam', 'hari', 'minggu', 'bulan', 'tahun', 'dekade',
        'abad', 'milenium', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu',
        'minggu', 'januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli',
        'agustus', 'september', 'oktober', 'november', 'desember',

        // ── Bagian Tubuh ─────────────────────────────────────
        'kepala', 'rambut', 'dahi', 'mata', 'hidung', 'telinga', 'mulut', 'bibir',
        'gigi', 'lidah', 'pipi', 'dagu', 'leher', 'bahu', 'lengan', 'siku',
        'tangan', 'jari', 'kuku', 'dada', 'perut', 'punggung', 'pinggang', 'pinggul',
        'paha', 'lutut', 'betis', 'kaki', 'tumit', 'telapak', 'otot', 'tulang',

        // ── Emosi & Perasaan ─────────────────────────────────
        'bahagia', 'sukacita', 'cinta', 'kasih', 'sayang', 'rindu', 'kangen', 'harapan',
        'mimpi', 'semangat', 'motivasi', 'inspirasi', 'ambisi', 'tekad', 'keyakinan',
        'kepercayaan', 'kesabaran', 'ketenangan', 'kedamaian', 'harmoni', 'kebebasan',
        'keadilan', 'kebenaran', 'kejujuran', 'kebaikan', 'keberanian', 'kehormatan',
        'kebijaksanaan', 'kerendahhatian', 'kesederhanaan', 'ketulusan', 'kesetiaan',
        'persahabatan', 'persaudaraan', 'kebersamaan', 'solidaritas', 'empati', 'simpati',
        'toleransi', 'pengertian', 'pemaafan', 'pengampunan', 'perdamaian', 'kerukunan',
        'kesuksesan', 'kemenangan', 'pencapaian', 'prestasi', 'rekor', 'medali', 'piala',

        // ── Alam & Lingkungan ────────────────────────────────
        'ekosistem', 'habitat', 'spesies', 'populasi', 'komunitas', 'biodiversitas',
        'konservasi', 'pelestarian', 'reboisasi', 'penghijauan', 'daur', 'limbah',
        'polusi', 'emisi', 'karbon', 'oksigen', 'nitrogen', 'hidrogen', 'fotosintesis',
        'organisme', 'mikroba', 'bakteri', 'virus', 'sel', 'gen', 'kromosom', 'evolusi',
        'adaptasi', 'mutasi', 'seleksi', 'ekologi', 'biosfer', 'atmosfer', 'litosfer',

        // ── Olahraga ─────────────────────────────────────────
        'sepakbola', 'basket', 'voli', 'badminton', 'tenis', 'renang', 'atletik',
        'tinju', 'karate', 'taekwondo', 'judo', 'silat', 'anggar', 'panahan',
        'golf', 'biliar', 'bowling', 'skateboard', 'surfing', 'panjat', 'mendaki',

        // ── Musik & Seni ─────────────────────────────────────
        'gitar', 'piano', 'drum', 'biola', 'seruling', 'harmonika', 'ukulele',
        'melodi', 'irama', 'ritme', 'akor', 'lirik', 'lagu', 'album',
        'konser', 'orkestra', 'band', 'genre', 'pop', 'rock', 'jazz',
        'dangdut', 'keroncong', 'gamelan', 'angklung', 'sasando', 'kolintang',

        // ── Transportasi & Perjalanan ────────────────────────
        'perjalanan', 'petualangan', 'ekspedisi', 'wisata', 'liburan', 'rekreasi',
        'destinasi', 'navigasi', 'koordinat', 'kompas', 'rute', 'jalur', 'trayek',
        'tiket', 'paspor', 'visa', 'bagasi', 'koper', 'ransel', 'tenda',

        // ── Bisnis & Ekonomi ─────────────────────────────────
        'perusahaan', 'korporasi', 'startup', 'bisnis', 'perdagangan', 'industri',
        'produksi', 'distribusi', 'konsumsi', 'investasi', 'saham', 'obligasi',
        'modal', 'laba', 'rugi', 'pendapatan', 'pengeluaran', 'anggaran', 'pajak',
        'inflasi', 'deflasi', 'resesi', 'pertumbuhan', 'devisa', 'ekspor', 'impor',
        'kontrak', 'negosiasi', 'kesepakatan', 'proposal', 'strategi', 'target',

        // ── Hukum & Pemerintahan ─────────────────────────────
        'konstitusi', 'undang-undang', 'peraturan', 'kebijakan', 'keputusan', 'dekrit',
        'demokrasi', 'republik', 'parlemen', 'kabinet', 'presiden', 'menteri', 'gubernur',
        'walikota', 'bupati', 'camat', 'lurah', 'legislatif', 'eksekutif', 'yudikatif',
        'pemilu', 'referendum', 'kampanye', 'partai', 'koalisi', 'oposisi', 'reformasi',

        // ── Kata Hubung & Preposisi ──────────────────────────
        'dan', 'atau', 'tetapi', 'namun', 'karena', 'sebab', 'akibat', 'maka',
        'jika', 'apabila', 'ketika', 'saat', 'sebelum', 'sesudah', 'selama', 'hingga',
        'untuk', 'demi', 'agar', 'supaya', 'dengan', 'tanpa', 'tentang', 'mengenai',
        'oleh', 'dari', 'kepada', 'terhadap', 'antara', 'dalam', 'luar', 'atas',
        'bawah', 'depan', 'belakang', 'samping', 'sekitar', 'sepanjang', 'melalui',
    ];

    /**
     * Mengambil sejumlah kata acak dari bank kosakata.
     *
     * @param int $count Jumlah kata yang diambil (default: 50)
     * @return string Kata-kata yang digabung dengan spasi
     */
    public static function getRandomWords(int $count = 50): string
    {
        $total = count(self::$words);
        $selected = [];

        for ($i = 0; $i < $count; $i++) {
            $selected[] = self::$words[array_rand(self::$words)];
        }

        return implode(' ', $selected);
    }
}
