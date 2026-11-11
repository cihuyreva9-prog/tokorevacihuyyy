
<?php
session_start();
require __DIR__.'/config.php';

try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(Exception $x) {
    exit('Database belum terhubung. Impor database.sql dan cek config.php.');
}

function q($s,$p=[]){
    global $pdo;
    $t=$pdo->prepare($s);
    $t->execute($p);
    return $t;
}

function rows($s,$p=[]){
    return q($s,$p)->fetchAll();
}

function row($s,$p=[]){
    return q($s,$p)->fetch();
}

function e($s){
    return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
}

function rp($n){
    return 'Rp '.number_format((float)$n,0,',','.');
}

function setting($k){
    static $c=null;
    if($c===null){
        $c=array_column(rows('SELECT k,v FROM settings'),'v','k');
    }
    return $c[$k]??'';
}

function user(){
    return $_SESSION['user']??null;
}

function go($u){
    header('Location: '.$u);
    exit;
}

function csrf(){
    return $_SESSION['csrf']??($_SESSION['csrf']=bin2hex(random_bytes(16)));
}

function chk(){
    if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){
        http_response_code(419);
        exit('Sesi tidak valid. Muat ulang halaman.');
    }
}

function tok(){
    return '<input type="hidden" name="csrf" value="'.csrf().'">';
}

function flash($m=null){
    if($m!==null){
        $_SESSION['flash']=$m;
        return '';
    }

    $f=$_SESSION['flash']??'';
    unset($_SESSION['flash']);
    return $f;
}

function badge($s){
    $m=[
        'belum_bayar'=>'Belum bayar',
        'diproses'=>'Diproses',
        'dikirim'=>'Dikirim',
        'selesai'=>'Selesai',
        'dibatalkan'=>'Dibatalkan'
    ];

    return '<span class="bd s-'.e($s).'">'.e($m[$s]??$s).'</span>';
}

function cart_items(){
    $c=$_SESSION['cart']??[];

    if(!$c) return [];

    $in=implode(',',array_map('intval',array_keys($c)));
    $o=[];

    foreach(rows("SELECT * FROM products WHERE active=1 AND id IN($in)") as $x){
        $x['qty']=min((int)$c[$x['id']],(int)$x['stock']);

        if($x['qty']>0) $o[]=$x;
    }

    return $o;
}

function totals($sub,$disc,$ship=0){
    $b=max(0,$sub-$disc);
    $tax=(int)round($b*(float)setting('tax_percent')/100);
    $raw=$b+$tax+$ship;
    $r=(int)setting('rounding');
    $t=$r?(int)(round($raw/$r)*$r):$raw;

    return [
        'sub'=>$sub,
        'disc'=>$disc,
        'tax'=>$tax,
        'ship'=>$ship,
        'rounding'=>$t-$raw,
        'total'=>$t
    ];
}

function voucher_discount($code,$sub){
    $code=strtoupper(trim((string)$code));

    if($code==='') return [0,''];

    $v=row('SELECT * FROM vouchers WHERE code=? AND active=1',[$code]);

    if(!$v) return [0,'Kode voucher tidak valid'];

    if($sub<$v['min_total']){
        return [0,'Voucher butuh minimal belanja '.rp($v['min_total'])];
    }

    $d=$v['type']==='percent'
        ?(int)round($sub*$v['value']/100)
        :(int)$v['value'];

    return [min($d,$sub),''];
}

// Semua nominal dihitung ulang di server.
function make_order($o,$items){
    global $pdo;

    $pdo->beginTransaction();

    try{
        $sub=0;
        $lines=[];

        foreach($items as $pid=>$qty){
            $qty=(int)$qty;

            if($qty<1) continue;

            $p=row(
                'SELECT * FROM products WHERE id=? AND active=1 FOR UPDATE',
                [(int)$pid]
            );

            if(!$p) throw new Exception('Produk tidak ditemukan');

            if($p['stock']<$qty){
                throw new Exception("Stok {$p['name']} tidak cukup (sisa {$p['stock']})");
            }

            $lines[]=[$p,$qty];
            $sub+=$p['price']*$qty;
        }

        if(!$lines) throw new Exception('Keranjang kosong');

        $vc=trim($o['voucher']??'');
        [$disc,$err]=voucher_discount($vc,$sub);

        if($vc!==''&&$err) throw new Exception($err);

        $disc=min(
            $sub,
            $disc+max(0,(int)($o['manual_discount']??0))
        );

        $t=totals($sub,$disc,(int)($o['shipping']??0));

        $pm=$o['payment_method'];
        $paid=$o['amount_paid']??null;

        if($o['source']==='pos'){
            if($pm==='tunai'){
                if((int)$paid<$t['total']){
                    throw new Exception('Uang diterima kurang dari total');
                }
            }else{
                $paid=$t['total'];
            }
        }

        $change=($pm==='tunai'||$pm==='cod')
            &&$paid!==null
            &&(int)$paid>=$t['total']
            ?(int)$paid-$t['total']:0;

        $code=($o['source']==='pos'?'POS-':'INV-')
            .date('Ymd').'-'
            .strtoupper(substr(bin2hex(random_bytes(3)),0,5));

        q(
            'INSERT INTO orders(
                code,source,user_id,cashier_id,shift_id,status,
                payment_method,subtotal,discount,tax,shipping,
                rounding,total,amount_paid,change_amount,
                name,phone,address,courier,voucher_code
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $code,
                $o['source'],
                $o['user_id']??null,
                $o['cashier_id']??null,
                $o['shift_id']??null,
                $o['status'],
                $pm,
                $sub,
                $disc,
                $t['tax'],
                $t['ship'],
                $t['rounding'],
                $t['total'],
                $paid,
                $change,
                $o['name']??null,
                $o['phone']??null,
                $o['address']??null,
                $o['courier']??null,
                $vc?:null
            ]
        );

        $id=$pdo->lastInsertId();

        foreach($lines as [$p,$qty]){
            q(
                'INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)',
                [$id,$p['id'],$p['name'],$p['price'],$qty]
            );

            q(
                'UPDATE products SET stock=stock-? WHERE id=?',
                [$qty,$p['id']]
            );

            q(
                'INSERT INTO stock_movements(product_id,qty,type,note) VALUES(?,?,?,?)',
                [$p['id'],-$qty,'penjualan',$code]
            );
        }

        if($o['source']==='pos'&&!empty($o['user_id'])){
            q(
                'UPDATE users SET points=points+? WHERE id=?',
                [(int)floor($t['total']/1000),$o['user_id']]
            );
        }

        $pdo->commit();

        return $code;

    }catch(Exception $x){
        $pdo->rollBack();
        throw $x;
    }
}

function set_status($id,$new){
    $o=row('SELECT * FROM orders WHERE id=?',[$id]);

    if(!$o||$o['status']===$new||$o['status']==='dibatalkan') return;

    if($new==='dibatalkan'){
        foreach(rows('SELECT * FROM order_items WHERE order_id=?',[$id]) as $i){
            q(
                'UPDATE products SET stock=stock+? WHERE id=?',
                [$i['qty'],$i['product_id']]
            );

            q(
                'INSERT INTO stock_movements(product_id,qty,type,note) VALUES(?,?,?,?)',
                [$i['product_id'],$i['qty'],'retur','Batal '.$o['code']]
            );
        }
    }

    if($new==='selesai'&&$o['user_id']&&$o['source']==='online'){
        q(
            'UPDATE users SET points=points+? WHERE id=?',
            [(int)floor($o['total']/1000),$o['user_id']]
        );
    }

    q(
        'UPDATE orders SET status=?,void_requested=0 WHERE id=?',
        [$new,$id]
    );
}

function home_of($r){
    return [
        'admin'=>'/admin/',
        'kasir'=>'/kasir/',
        'pembeli'=>'/akun/'
    ][$r]??'/';
}

function rkey($c){
    return substr(md5($c.DB_PASS.'tokoku-key'),0,10);
}

function need($roles){
    $roles=(array)$roles;
    $u=user();

    if(!$u) go(BASE.home_of($roles[0]).'login.php');

    if(!in_array($u['role'],$roles,true)){
        http_response_code(403);

        exit(
            '<!doctype html>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link rel="stylesheet" href="'.BASE.'/assets/style.css">
            <div class="card" style="max-width:440px;margin:60px auto">
                <h2>Halaman ini bukan untuk peran Anda</h2>
                <p>Anda masuk sebagai <b>'.e($u['role']).'</b>.</p>
                <a class="btn" href="'.BASE.home_of($u['role']).'">Ke dashboard saya</a>
                <a class="btn ghost" href="'.BASE.home_of($u['role']).'logout.php">Keluar</a>
            </div>'
        );
    }
}

function timeline($s,$src='online'){
    if($src==='pos') return '';

    if($s==='dibatalkan'){
        return '<div class="steps"><b class="x">Pesanan dibatalkan</b></div>';
    }

    $i=[
        'belum_bayar'=>0,
        'diproses'=>1,
        'dikirim'=>2,
        'selesai'=>3
    ][$s]??0;

    $o='<div class="steps">';

    foreach(['Pesanan dibuat','Dibayar & dikemas','Dikirim','Selesai'] as $k=>$t){
        $o.='<b class="'.($k<=$i?'on':'').'">'.($k+1).'. '.$t.'</b>';
    }

    return $o.'</div>';
}

function login_as($r){
    session_regenerate_id(true);

    $_SESSION['user']=[
        'id'=>$r['id'],
        'name'=>$r['name'],
        'role'=>$r['role']
    ];

    go(BASE.home_of($r['role']));
}

// Halaman login terpisah untuk tiap peran.
function login_page($role,$label,$hint,$reg=false){
    $u=user();

    if($u&&$u['role']===$role) go(BASE.home_of($role));

    $err='';
    $tab=$reg&&isset($_GET['daftar']);

    if($_SERVER['REQUEST_METHOD']==='POST'){
        chk();

        $tab=$reg&&($_POST['mode']??'')==='daftar';

        if($tab){
            $n=trim($_POST['name']);
            $em=trim($_POST['email']);

            if(!$n||!filter_var($em,FILTER_VALIDATE_EMAIL)||strlen($_POST['password'])<6){
                $err='Isi nama, email yang valid, dan password minimal 6 karakter.';
            }elseif(row('SELECT id FROM users WHERE email=?',[$em])){
                $err='Email sudah terdaftar. Silakan masuk.';
            }else{
                q(
                    'INSERT INTO users(name,email,password,phone,address,role) VALUES(?,?,?,?,?,?)',
                    [
                        $n,
                        $em,
                        password_hash($_POST['password'],PASSWORD_DEFAULT),
                        trim($_POST['phone']),
                        trim($_POST['address']),
                        'pembeli'
                    ]
                );

                login_as(row('SELECT * FROM users WHERE email=?',[$em]));
            }

        }else{
            $r=row('SELECT * FROM users WHERE email=?',[trim($_POST['email'])]);

            if(!$r||!$r['active']||!password_verify($_POST['password'],$r['password'])){
                $err='Email atau password salah, atau akun diblokir.';
            }elseif($r['role']!==$role){
                $err='Akun ini terdaftar sebagai '.$r['role'].'. Gunakan halaman masuk yang sesuai.';
            }else{
                login_as($r);
            }
        }
    }

    $sn=e(setting('store_name'));

    $to=[
        'pembeli'=>['/akun/','Pembeli'],
        'kasir'=>['/kasir/','Kasir'],
        'admin'=>['/admin/','Admin']
    ];

    echo '<!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Masuk '.e($label).' - '.$sn.'</title>
        <link rel="stylesheet" href="'.BASE.'/assets/style.css">
    </head>
    <body class="auth '.$role.'">
    <main class="authbox">
        <a class="logo2" href="'.BASE.'/index.php">'.$sn.'</a>
        <div class="card">
            <h1>'.($tab?'Daftar akun pembeli':'Masuk '.e($label)).'</h1>';

    if($err){
        echo '<div class="flash" role="alert">'.e($err).'</div>';
    }

    echo '<form method="post">'.tok().'
        <input type="hidden" name="mode" value="'.($tab?'daftar':'login').'">';

    if($tab){
        echo '
        <label>Nama</label>
        <input name="name" required>

        <label>No. HP</label>
        <input name="phone" inputmode="tel">

        <label>Alamat pengiriman</label>
        <textarea name="address" rows="2"></textarea>';
    }

    echo '
        <label>Email</label>
        <input type="email" name="email" required autofocus>

        <label>Password'.($tab?' (min. 6 karakter)':'').'</label>
        <input type="password" name="password" required>
        <br><br>
        <button style="width:100%">'.($tab?'Daftar dan masuk':'Masuk').'</button>
    </form>';

    if($reg){
        echo '<p class="mut c">'.(
            $tab
            ?'Sudah punya akun? <a href="login.php">Masuk</a>'
            :'Belum punya akun? <a href="login.php?daftar=1">Daftar gratis</a>'
        ).'</p>';
    }

    echo '<p class="mut c">Demo: '.e($hint).'</p>
        </div>
        <p class="mut c">Masuk sebagai peran lain: ';

    foreach($to as $k=>$v){
        if($k!==$role){
            echo '<a href="'.BASE.$v[0].'login.php">'.$v[1].'</a> ';
        }
    }

    echo '· <a href="'.BASE.'/index.php">Kembali ke toko</a>
        </p>
    </main>
    </body>
    </html>';

    exit;
}

// =====================================================
// HEAD: SATU LAYOUT SIDEBAR DAN KONTEN
// =====================================================

function head($title,$area='toko',$menu=[],$cur=''){
    $u=user();
    $sn=e(setting('store_name'));

    // Permintaan AJAX hanya mengeluarkan konten.
    $partial=isset($_GET['_partial'])&&$_GET['_partial']==='1';

    if($partial){
        if($f=flash()){
            echo '<div class="flash" role="status">'.e($f).'</div>';
        }
        return;
    }

    echo '<!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>'.e($title).' - '.$sn.'</title>
        <link rel="stylesheet" href="'.BASE.'/assets/style.css">
    </head>
    <body class="'.$area.'">';

    if($area==='toko'){
        echo '<header class="top">
            <div class="in">
                <a class="logo" href="'.BASE.'/index.php">'.$sn.'</a>
                <nav>
                    <a href="#keranjang">Keranjang <b class="cnt" id="cn">0</b></a>
                    <a href="#lacak">Lacak pesanan</a>';

        if($u){
            echo '<a href="'.BASE.home_of($u['role']).'">'.
                ($u['role']==='pembeli'?'Akun saya':'Dashboard').'</a>
                <a class="btn sm alt" href="'.BASE.home_of($u['role']).'logout.php">Keluar</a>';
        }else{
            echo '<a href="'.BASE.'/akun/login.php">Masuk</a>
                <a class="btn sm alt" href="'.BASE.'/akun/login.php?daftar=1">Daftar</a>';
        }

        echo '</nav></div></header><main class="wrap">';

    }else{
        echo '<div class="dash">
            <aside>
                <div class="logo">'.$sn.'
                    <small>Area '.e(ucfirst($area)).'</small>
                </div>

                <div class="who2">
                    <b>'.e($u['name']??'').'</b>
                    <a class="btn sm alt" href="'.BASE.home_of($u['role']??$area).'logout.php">Keluar</a>
                </div>

                <nav>';

        foreach($menu as $k=>$l){
            echo '<a href="?v='.e($k).'" data-menu="'.e($k).'"'.
                ($cur===$k?' class="on"':'').'>'.e($l).'</a>';
        }

        if(($u['role']??'')==='admin'&&$area==='admin'){
            echo '<a href="'.BASE.'/kasir/">Buka mesin kasir</a>';
        }

        echo '</nav>
            <div class="who">
                <a href="'.BASE.'/index.php">Lihat toko</a>
            </div>
            </aside>

            <main id="main-content">';
    }

    if($f=flash()){
        echo '<div class="flash" role="status">'.e($f).'</div>';
    }
}

// =====================================================
// FOOT: NAVIGASI AJAX TANPA MENGHILANGKAN SIDEBAR
// =====================================================

function foot($area='toko'){

    if(isset($_GET['_partial'])&&$_GET['_partial']==='1'){
        return;
    }

    if($area==='toko'){
        echo '</main>
        <footer class="ft">
            <b>'.e(setting('store_name')).'</b> ·
            '.e(setting('store_address')).' ·
            '.e(setting('store_phone')).'
            <br>
            <a href="'.BASE.'/kasir/login.php">Masuk kasir</a> ·
            <a href="'.BASE.'/admin/login.php">Masuk admin</a>
        </footer>';

    }else{
        echo '</main></div>';

        echo <<<'HTML'
<script>
(function(){
    const main = document.getElementById('main-content');

    if (!main) return;

    let loading = false;

    function updateMenu(url){
        const current = url.searchParams.get('v') || 'home';

        document.querySelectorAll('aside nav a[data-menu]').forEach(link => {
            link.classList.toggle('on',link.dataset.menu === current);
        });
    }

    async function navigate(url,push=true){
        if(loading) return;

        const target = new URL(url,location.href);

        if(target.origin!==location.origin ||
           target.pathname!==location.pathname){
            location.href=target.href;
            return;
        }

        const originalUrl=new URL(target.href);
        originalUrl.searchParams.delete('_partial');

        const requestUrl=new URL(originalUrl.href);
        requestUrl.searchParams.set('_partial','1');

        loading=true;
        main.style.opacity='0.5';

        try{
            const response=await fetch(requestUrl.href,{
                headers:{
                    'X-Requested-With':'XMLHttpRequest'
                }
            });

            if(!response.ok){
                throw new Error('Gagal memuat halaman');
            }

            const html=await response.text();

            // Jika halaman memiliki script khusus, muat secara normal.
            if(/<script[\s>]/i.test(html)){
                location.href=originalUrl.href;
                return;
            }

            main.innerHTML=html;

            if(push){
                history.pushState(
                    {url:originalUrl.href},
                    '',
                    originalUrl.pathname+
                    originalUrl.search+
                    originalUrl.hash
                );
            }

            updateMenu(originalUrl);
            main.scrollTop=0;
            window.scrollTo(0,0);

        }catch(error){
            location.href=originalUrl.href;

        }finally{
            loading=false;
            main.style.opacity='1';
        }
    }

    document.addEventListener('click',function(event){
        const link=event.target.closest('a');

        if(!link) return;
        if(link.target&&link.target!=='_self') return;
        if(link.hasAttribute('download')) return;

        const url=new URL(link.href,location.href);

        if(url.origin!==location.origin) return;
        if(url.pathname!==location.pathname) return;
        if(!url.searchParams.has('v')) return;

        event.preventDefault();
        navigate(url.href);
    });

    window.addEventListener('popstate',function(){
        navigate(location.href,false);
    });

    updateMenu(new URL(location.href));
})();
</script>
HTML;
    }

    echo '</body></html>';
}
