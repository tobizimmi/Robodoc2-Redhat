<?php
declare(strict_types=1);

/**
 * QR Code SVG generator — pure PHP, no dependencies.
 * Based on the reference implementation by Kazuhiko Arase (MIT License).
 * Generates scannable QR codes for otpauth:// URIs.
 */
class QrCode
{
    public static function svg(string $data, int $size = 200): string
    {
        $qr = self::encode($data);
        if (!$qr) return '';
        $n   = count($qr);
        $mod = max(2, (int)floor($size / ($n + 8)));
        $qz  = $mod * 4;
        $dim = $n * $mod + $qz * 2;
        $out = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$dim}\" height=\"{$dim}\">";
        $out .= "<rect width=\"{$dim}\" height=\"{$dim}\" fill=\"#fff\"/>";
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($qr[$r][$c] & 1) {
                    $x = $qz + $c * $mod;
                    $y = $qz + $r * $mod;
                    $out .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$mod}\" height=\"{$mod}\" fill=\"#000\"/>";
                }
            }
        }
        return $out . '</svg>';
    }

    // ── QR encoder (byte mode, EC level M) ────────────────────────────────
    private static function encode(string $data): ?array
    {
        $len = strlen($data);
        // Version selection for byte mode, EC level M
        $caps = [1=>14,2=>26,3=>42,4=>62,5=>84,6=>106,7=>122,8=>152,9=>180,10=>213];
        $ver  = null;
        foreach ($caps as $v => $c) { if ($len <= $c) { $ver = $v; break; } }
        if (!$ver) return null;

        $size = 21 + ($ver - 1) * 4;
        $mat  = array_fill(0, $size, array_fill(0, $size, 0));
        $res  = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFinder($mat, $res, 0,        0,        $size);
        self::placeFinder($mat, $res, $size - 7, 0,        $size);
        self::placeFinder($mat, $res, 0,        $size - 7, $size);
        self::placeTiming($mat, $res, $size);
        if ($ver >= 2) self::placeAlignment($mat, $res, $ver);
        self::placeDark($mat, $res, $ver);
        self::reserveFormat($res, $size);

        $cw = self::buildCodewords($data, $ver);
        self::placeCodewords($mat, $res, $cw, $size);

        // Find best mask
        $best = null; $score = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $tmp = self::applyMask($mat, $res, $size, $m);
            $s   = self::penalty($tmp, $size);
            if ($s < $score) { $score = $s; $best = $tmp; $bm = $m; }
        }
        self::writeFormat($best, $size, $bm ?? 0);
        return $best;
    }

    private static function placeFinder(array &$m, array &$r, int $row, int $col, int $size): void
    {
        static $pat = [[1,1,1,1,1,1,1],[1,0,0,0,0,0,1],[1,0,1,1,1,0,1],
                       [1,0,1,1,1,0,1],[1,0,1,1,1,0,1],[1,0,0,0,0,0,1],[1,1,1,1,1,1,1]];
        for ($i = 0; $i < 7; $i++) for ($j = 0; $j < 7; $j++) {
            if ($row+$i < $size && $col+$j < $size) {
                $m[$row+$i][$col+$j] = $pat[$i][$j];
                $r[$row+$i][$col+$j] = true;
            }
        }
        // Separator (1 module white border)
        for ($k = -1; $k <= 7; $k++) {
            foreach ([[$row+7,$col+$k],[$row-1,$col+$k],[$row+$k,$col+7],[$row+$k,$col-1]] as [$ri,$ci]) {
                if ($ri >= 0 && $ri < $size && $ci >= 0 && $ci < $size && !$r[$ri][$ci]) {
                    $m[$ri][$ci] = 0; $r[$ri][$ci] = true;
                }
            }
        }
    }

    private static function placeTiming(array &$m, array &$r, int $sz): void
    {
        for ($i = 8; $i < $sz - 8; $i++) {
            if (!$r[6][$i]) { $m[6][$i] = $i%2?0:1; $r[6][$i] = true; }
            if (!$r[$i][6]) { $m[$i][6] = $i%2?0:1; $r[$i][6] = true; }
        }
    }

    private static function placeDark(array &$m, array &$r, int $ver): void
    {
        $row = 4*$ver+9; $col = 8;
        $m[$row][$col] = 1; $r[$row][$col] = true;
    }

    private static function placeAlignment(array &$m, array &$r, int $ver): void
    {
        $t = [2=>[6,18],3=>[6,22],4=>[6,26],5=>[6,30],6=>[6,34],
              7=>[6,22,38],8=>[6,24,42],9=>[6,26,46],10=>[6,28,50]];
        $pos = $t[$ver] ?? [];
        foreach ($pos as $row) foreach ($pos as $col) {
            if ($r[$row][$col]) continue;
            for ($i=-2;$i<=2;$i++) for ($j=-2;$j<=2;$j++) {
                $v = (abs($i)==2||abs($j)==2||(!$i&&!$j))?1:0;
                $m[$row+$i][$col+$j]=$v; $r[$row+$i][$col+$j]=true;
            }
        }
    }

    private static function reserveFormat(array &$r, int $sz): void
    {
        for ($i=0;$i<=8;$i++) { $r[8][$i]=true; $r[$i][8]=true; }
        for ($i=$sz-8;$i<$sz;$i++) { $r[8][$i]=true; $r[$i][8]=true; }
    }

    private static function buildCodewords(string $data, int $ver): array
    {
        // Data capacity (bytes) and EC count for each version (level M)
        $dataCap = [1=>14,2=>26,3=>42,4=>62,5=>84,6=>106,7=>122,8=>152,9=>180,10=>213];
        $ecCount = [1=>10,2=>16,3=>26,4=>36,5=>48,6=>64,7=>72,8=>88,9=>110,10=>130];
        $dc = $dataCap[$ver]; $ec = $ecCount[$ver];

        // Build bit stream
        $bits = [0,1,0,0]; // Mode: byte
        $len  = strlen($data);
        for ($i=7;$i>=0;$i--) $bits[] = ($len>>$i)&1;
        foreach (str_split($data) as $ch) {
            $b = ord($ch);
            for ($i=7;$i>=0;$i--) $bits[] = ($b>>$i)&1;
        }
        // Pad to byte boundary
        while (count($bits)%8) $bits[] = 0;
        // Pad bytes
        $pads=[0xEC,0x11]; $pi=0;
        while (count($bits)<$dc*8) {
            $p=$pads[$pi++%2];
            for ($i=7;$i>=0;$i--) $bits[] = ($p>>$i)&1;
        }
        $bytes = [];
        for ($i=0;$i<$dc;$i++) {
            $b=0; for ($j=0;$j<8;$j++) $b=($b<<1)|($bits[$i*8+$j]??0);
            $bytes[] = $b;
        }
        return array_merge($bytes, self::rs($bytes, $ec));
    }

    // Reed-Solomon error correction
    private static function rs(array $data, int $ecLen): array
    {
        static $EXP=[], $LOG=[];
        if (!$EXP) {
            $x=1;
            for ($i=0;$i<255;$i++) {
                $EXP[$i]=$x; $LOG[$x]=$i;
                $x<<=1; if ($x&256) $x^=285;
            }
            for ($i=255;$i<512;$i++) $EXP[$i]=$EXP[$i-255];
        }
        $mul = fn($a,$b)=>($a&&$b)?$EXP[$LOG[$a]+$LOG[$b]]:0;

        // Generator polynomial
        $g=[1];
        for ($i=0;$i<$ecLen;$i++) {
            $a=$EXP[$i]; $g2=array_fill(0,count($g)+1,0);
            foreach ($g as $k=>$v) { $g2[$k]^=$v; $g2[$k+1]^=$mul($v,$a); }
            $g=$g2;
        }
        // Divide
        $msg=array_merge($data,array_fill(0,$ecLen,0));
        for ($i=0;$i<count($data);$i++) {
            $c=$msg[$i];
            if ($c) foreach ($g as $k=>$v) $msg[$i+$k]^=$mul($v,$c);
        }
        return array_slice($msg,count($data));
    }

    private static function placeCodewords(array &$m, array $r, array $cw, int $sz): void
    {
        $bits=[];
        foreach ($cw as $b) for ($i=7;$i>=0;$i--) $bits[]=($b>>$i)&1;
        $idx=0; $up=true;
        for ($col=$sz-1;$col>=1;$col-=2) {
            if ($col==6) $col=5;
            for ($i=0;$i<$sz;$i++) {
                $row=$up?$sz-1-$i:$i;
                for ($dc=0;$dc<=1;$dc++) {
                    $c=$col-$dc;
                    if (!$r[$row][$c]&&$idx<count($bits)) $m[$row][$c]=$bits[$idx++];
                }
            }
            $up=!$up;
        }
    }

    private static function applyMask(array $m, array $r, int $sz, int $p): array
    {
        $fn = [
            fn($i,$j)=>($i+$j)%2===0,   fn($i,$j)=>$i%2===0,
            fn($i,$j)=>$j%3===0,         fn($i,$j)=>($i+$j)%3===0,
            fn($i,$j)=>(intdiv($i,2)+intdiv($j,3))%2===0,
            fn($i,$j)=>($i*$j)%2+($i*$j)%3===0,
            fn($i,$j)=>(($i*$j)%2+($i*$j)%3)%2===0,
            fn($i,$j)=>(($i+$j)%2+($i*$j)%3)%2===0,
        ][$p];
        $o=$m;
        for ($i=0;$i<$sz;$i++) for ($j=0;$j<$sz;$j++)
            if (!$r[$i][$j]&&$fn($i,$j)) $o[$i][$j]^=1;
        return $o;
    }

    private static function penalty(array $m, int $sz): int
    {
        $p=0;
        for ($i=0;$i<$sz;$i++) {
            $rr=1; $rc=1;
            for ($j=1;$j<$sz;$j++) {
                if ($m[$i][$j]===$m[$i][$j-1]) { $rr++; if($rr==5)$p+=3;elseif($rr>5)$p++; } else $rr=1;
                if ($m[$j][$i]===$m[$j-1][$i]) { $rc++; if($rc==5)$p+=3;elseif($rc>5)$p++; } else $rc=1;
            }
            for ($j=0;$j<$sz-1;$j++) if ($m[$i][$j]===$m[$i][$j+1]&&$m[$i][$j]===(isset($m[$i+1])?$m[$i+1][$j]:!$m[$i][$j])&&$m[$i][$j]===(isset($m[$i+1])?$m[$i+1][$j+1]:!$m[$i][$j])) $p+=3;
        }
        return $p;
    }

    private static function writeFormat(array &$m, int $sz, int $mask): void
    {
        // EC=M(01), mask
        $d=(0b01<<3)|$mask; $r=$d<<10;
        for ($i=14;$i>=10;$i--) if ($r&(1<<$i)) $r^=(0b10100110111<<($i-10));
        $f=(($d<<10)|$r)^0b101010000010010;
        $b=[]; for ($i=14;$i>=0;$i--) $b[]=($f>>$i)&1;
        // Top-left
        $seq=[0,1,2,3,4,5,7,8, 7,5,4,3,2,1,0];
        for ($i=0;$i<8;$i++) { $m[8][$seq[$i]]=$b[$i]; $m[$seq[$i+7]][8]=$b[$i]; }
        $m[8][$sz-8]=$b[7];
        for ($i=8;$i<15;$i++) { $m[8][$sz-15+$i]=$b[$i]; $m[$sz-15+$i][8]=$b[$i]; }
        // Dark module (stays 1)
        $m[$sz-8][8]=1;
    }
}
