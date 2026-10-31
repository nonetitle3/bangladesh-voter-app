<?php
declare(strict_types=1);

function legacy_data(string $relative): ?array {
    $file = app_root('../backend/data/' . ltrim($relative, '/'));
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}
function public_stats(): array {
    $idx = legacy_data('index.json') ?? ['divisions'=>[],'totalVoters'=>0,'lastUpdated'=>null];
    $gender = ['male'=>0,'female'=>0];
    try {
        $row = db()->query("SELECT COUNT(*) total, SUM(gender IN ('male','পুরুষ')) male, SUM(gender IN ('female','মহিলা')) female FROM voter_records")->fetch();
        if ((int)$row['total'] > 0) $gender = ['male'=>(int)$row['male'], 'female'=>(int)$row['female']];
        return ['total'=>(int)$row['total'] ?: (int)($idx['totalVoters'] ?? 0), 'male'=>$gender['male'], 'female'=>$gender['female'], 'divisions'=>count($idx['divisions'] ?? []), 'lastUpdated'=>$idx['lastUpdated'] ?? null];
    } catch (Throwable $e) {
        return ['total'=>(int)($idx['totalVoters'] ?? 0),'male'=>0,'female'=>0,'divisions'=>count($idx['divisions'] ?? []),'lastUpdated'=>$idx['lastUpdated'] ?? null];
    }
}
function hierarchy_route(string $path): never {
    $parts = array_values(array_filter(explode('/', $path), 'strlen'));
    $type = $parts[1] ?? '';
    $division = $parts[2] ?? ''; $district = $parts[3] ?? ''; $upazila = $parts[4] ?? ''; $union = $parts[5] ?? '';
    if ($type === 'divisions') {
        $idx = legacy_data('index.json') ?? ['divisions'=>[]];
        $items=[]; foreach (($idx['divisions'] ?? []) as $id=>$v) $items[]=['id'=>$id]+$v;
        json_response(['count'=>count($items),'divisions'=>$items]);
    }
    if ($type === 'districts') {
        $idx=legacy_data("divisions/{$division}/index.json");
        if (!$idx) json_response(['error'=>'Division not found'],404);
        $items=[]; foreach (($idx['districts'] ?? []) as $id=>$v) $items[]=['id'=>$id]+(is_array($v)?$v:[]);
        json_response(['count'=>count($items),'districts'=>$items]);
    }
    if ($type === 'upazilas') {
        $idx=legacy_data("divisions/{$division}/{$district}/index.json");
        if (!$idx) json_response(['error'=>'District not found'],404);
        $items=[]; foreach (($idx['upazilas'] ?? []) as $id=>$v) $items[]=['id'=>$id]+(is_array($v)?$v:[]);
        json_response(['count'=>count($items),'upazilas'=>$items]);
    }
    if ($type === 'unions') {
        $dir=app_root("../backend/data/divisions/{$division}/{$district}/{$upazila}");
        if (!is_dir($dir)) json_response(['error'=>'Upazila not found'],404);
        $items=[];
        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $d) { $id=basename($d); $idx=legacy_data("divisions/{$division}/{$district}/{$upazila}/{$id}/index.json") ?? []; $items[]=['id'=>$id]+$idx; }
        json_response(['count'=>count($items),'unions'=>$items]);
    }
    if ($type === 'wards') {
        $dir=app_root("../backend/data/divisions/{$division}/{$district}/{$upazila}/{$union}");
        $wards=array_map(fn($f)=>basename($f,'.json'),glob($dir.'/*.json') ?: []);
        $wards=array_values(array_filter($wards,fn($x)=>$x!=='index'));
        json_response(['count'=>count($wards),'wards'=>$wards]);
    }
    if ($type === 'voters') {
        $ward=$parts[6] ?? '';
        $file=app_root("../backend/data/divisions/{$division}/{$district}/{$upazila}/{$union}/{$ward}.json");
        $voters=is_file($file)?(json_decode((string)file_get_contents($file),true) ?: []):[];
        $voters=array_values(array_filter($voters,function($v){$q=$_GET;return empty($q['gender'])||($v['gender']??'')===$q['gender'];}));
        $page=max(1,(int)($_GET['page']??1));$limit=min(200,max(1,(int)($_GET['limit']??50)));
        json_response(['count'=>count(array_slice($voters,($page-1)*$limit,$limit)),'total'=>count($voters),'page'=>$page,'totalPages'=>(int)ceil(count($voters)/$limit),'voters'=>array_slice($voters,($page-1)*$limit,$limit)]);
    }
    json_response(['error'=>'Not found'],404);
}
function public_search(array $q): array {
    $term=mb_strtolower(trim((string)($q['q']??'')));
    $division=(string)($q['division']??'');
    if ($term==='' && $division==='') json_response(['error'=>'Query (q) or division required'],400);
    $params=[];$where=[];
    if ($term!=='') { $like='%'.$term.'%';$where[]='(LOWER(name) LIKE ? OR LOWER(father_name) LIKE ? OR LOWER(mother_name) LIKE ? OR voter_id LIKE ? OR LOWER(address) LIKE ?)';array_push($params,$like,$like,$like,$like,$like); }
    foreach (['division','district','upazila','union_name','ward','gender'] as $field) if (!empty($q[$field])) { $where[]="$field LIKE ?";$params[]='%'.$q[$field].'%'; }
    if ($where) {
        try { $stmt=db()->prepare('SELECT voter_id AS voterId, serial_no AS serialNo, name, father_name AS fatherName, mother_name AS motherName, birth_date AS dob, gender, occupation, address, village, ward, union_name AS union, upazila, district, division, pdf_filename, page_number FROM voter_records WHERE '.implode(' AND ',$where).' ORDER BY id DESC LIMIT ?');foreach($params as $i=>$v)$stmt->bindValue($i+1,$v);$stmt->bindValue(count($params)+1,min(200,max(1,(int)($q['limit']??50))),PDO::PARAM_INT);$stmt->execute();$rows=$stmt->fetchAll();if($rows)return ['count'=>count($rows),'searchedFiles'=>0,'voters'=>$rows]; } catch(Throwable $e) {}
    }
    $idx=legacy_data('index.json') ?? ['divisions'=>[]];$results=[];
    $divs=$division?[$division]:array_keys($idx['divisions']??[]);
    foreach($divs as $div){$di=legacy_data("divisions/{$div}/index.json")??[];$dists=!empty($q['district'])?[$q['district']]:array_keys($di['districts']??[]);
      foreach($dists as $dist){$ui=legacy_data("divisions/{$div}/{$dist}/index.json")??[];$upzs=!empty($q['upazila'])?[$q['upazila']]:array_keys($ui['upazilas']??[]);
        foreach($upzs as $upz){$base=app_root("../backend/data/divisions/{$div}/{$dist}/{$upz}");$unions=!empty($q['union'])?[$q['union']]:array_map('basename',glob($base.'/*',GLOB_ONLYDIR)?:[]);
          foreach($unions as $un){$ud=$base.'/'.$un;$wards=!empty($q['ward'])?[$q['ward'].'.json']:array_map('basename',glob($ud.'/*.json')?:[]);foreach($wards as $wf){if($wf==='index.json')continue;$data=json_decode((string)@file_get_contents($ud.'/'.$wf),true)?:[];foreach($data as $v){if($term!==''&&!str_contains(mb_strtolower(json_encode($v,JSON_UNESCAPED_UNICODE)), $term))continue;if(!empty($q['gender'])&&($v['gender']??'')!==$q['gender'])continue;$results[]=$v;if(count($results)>=min(200,max(1,(int)($q['limit']??50))))break 5;}}}
        }
      }
    }
    return ['count'=>count($results),'searchedFiles'=>0,'voters'=>$results];
}
function voter_filter(array $q): array {
    $where=[];$values=[];
    if (!empty($q['q'])) { $like='%'.$q['q'].'%';$where[]='(name LIKE ? OR father_name LIKE ? OR mother_name LIKE ? OR voter_id LIKE ? OR district LIKE ? OR address LIKE ?)';array_push($values,$like,$like,$like,$like,$like,$like); }
    foreach(['name','father_name','mother_name','voter_id','district','upazila','union_name','ward','occupation'] as $f) if(!empty($q[$f])){$where[]="$f LIKE ?";$values[]='%'.$q[$f].'%';}
    if(!empty($q['gender'])){$where[]='gender=?';$values[]=$q['gender'];}
    if(!empty($q['page_number'])){$where[]='page_number=?';$values[]=(int)$q['page_number'];}
    return [$where?' WHERE '.implode(' AND ',$where):'',$values];
}
function parse_pdf_records(string $file, string $filename): array {
    $parser=new Smalot\PdfParser\Parser();$pdf=$parser->parseFile($file);$text=$pdf->getText();$pages=$pdf->getPages();$ocr=false;
    if (mb_strlen(trim($text)) < 25 && function_exists('exec')) {
        $prefix=tempnam(sys_get_temp_dir(),'voterpdf');@unlink($prefix);
        exec('pdftoppm -f 1 -l 30 -r 250 -png '.escapeshellarg($file).' '.escapeshellarg($prefix).' 2>/dev/null');
        foreach(glob($prefix.'-*.png')?:[] as $img){$out=[];$code=1;exec('tesseract '.escapeshellarg($img).' stdout -l ben+eng 2>/dev/null',$out,$code);if($code===0)$text.="\n".implode("\n",$out);@unlink($img);}
        $ocr=true;
    }
    $records=[];$bn='০১২৩৪৫৬৭৮৯';
    preg_match_all('/(?:^|\n)\s*([০-৯0-9]{1,6})\.\s*নাম\s*:/u',$text,$matches,PREG_OFFSET_CAPTURE);
    $starts=$matches[0]??[];
    for($i=0;$i<count($starts);$i++){
      $offset=$starts[$i][1];$block=substr($text,$offset,$i+1<count($starts)?$starts[$i+1][1]-$offset:strlen($text));
      $field=function(string $label,array $next=[])use($block){$end=$next?'(?='.implode('|',array_map(fn($x)=>preg_quote($x,'/'),$next)).'|$)':'$';if(preg_match('/'.preg_quote($label,'/').'\s*:\s*(.*?)'.$end.'/isu',$block,$m))return clean_text($m[1]);return '';};
      $serial=bn_digits($matches[1][$i][0]??'');
      $records[]=['serial_no'=>$serial,'voter_id'=>bn_digits($field('ভোটার নং',['পিতা'])),'name'=>$field('নাম',['ভোটার নং']),'father_name'=>$field('পিতা',['মাতা']),'mother_name'=>$field('মাতা',['পেশা','জন্ম তারিখ']),'occupation'=>$field('পেশা',['জন্ম তারিখ']),'birth_date'=>bn_digits($field('জন্ম তারিখ',['ঠিকানা'])),'address'=>$field('ঠিকানা'),'pdf_filename'=>$filename,'raw_text'=>$block,'confidence'=>$ocr?0.55:0.9];
    }
    return ['records'=>array_values(array_filter($records,fn($r)=>$r['name']!==''||$r['voter_id']!=='')),'pages'=>count($pages),'ocr'=>$ocr];
}
function admin_route(string $path,string $method): never {
    if($path==='/api/admin/me') json_response(['authenticated'=>true]);
    if($path==='/api/admin/dashboard'&&$method==='GET'){
      $pdo=db();$total=(int)$pdo->query('SELECT COUNT(*) FROM voter_records')->fetchColumn();$pdfs=(int)$pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();
      $group=fn($field)=>$pdo->query("SELECT COALESCE(NULLIF($field,''),'Unknown') name,COUNT(*) count FROM voter_records GROUP BY $field ORDER BY count DESC LIMIT 8")->fetchAll();
      json_response(['total_pdfs'=>$pdfs,'total_records'=>$total,'top_districts'=>$group('district'),'top_occupations'=>$group('occupation'),'gender_breakdown'=>$group('gender')]);
    }
    if($path==='/api/admin/records'&&$method==='GET'){
      [$where,$vals]=voter_filter($_GET);$page=max(1,(int)($_GET['page']??1));$size=min(200,max(1,(int)($_GET['page_size']??50)));$pdo=db();
      $count=$pdo->prepare('SELECT COUNT(*) FROM voter_records'.$where);$count->execute($vals);$stmt=$pdo->prepare('SELECT * FROM voter_records'.$where.' ORDER BY id DESC LIMIT ? OFFSET ?');foreach($vals as $i=>$v)$stmt->bindValue($i+1,$v);$stmt->bindValue(count($vals)+1,$size,PDO::PARAM_INT);$stmt->bindValue(count($vals)+2,($page-1)*$size,PDO::PARAM_INT);$stmt->execute();
      json_response(['records'=>$stmt->fetchAll(),'total_count'=>(int)$count->fetchColumn(),'page'=>$page,'page_size'=>$size,'total_pages'=>(int)ceil((int)$count->fetchColumn()/$size)]);
    }
    if(($path==='/api/admin/upload'||$path==='/api/admin/preview-pdf')&&$method==='POST') {
      if(empty($_FILES['files'])) json_response(['error'=>'Select one or more PDF files'],400);
      $batch=[];$pdo=db();$files=$_FILES['files'];$n=is_array($files['name'])?count($files['name']):1;
      for($i=0;$i<$n;$i++){
        $name=is_array($files['name'])?$files['name'][$i]:$files['name'];$tmp=is_array($files['tmp_name'])?$files['tmp_name'][$i]:$files['tmp_name'];$err=is_array($files['error'])?$files['error'][$i]:$files['error'];
        if($err!==UPLOAD_ERR_OK||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf')continue;
        $safe=preg_replace('/[^A-Za-z0-9._-]/','_',basename($name));$stored=app_root('storage/pdfs/'.bin2hex(random_bytes(8)).'-'.$safe);if(!move_uploaded_file($tmp,$stored))continue;
        try{$parsed=parse_pdf_records($stored,$name);$batch[]=['filename'=>$name,'path'=>$stored,'parsed'=>$parsed];}catch(Throwable $e){$batch[]=['filename'=>$name,'path'=>$stored,'error'=>$e->getMessage()];}
      }
      $previewId=bin2hex(random_bytes(16));$previewFile=app_root('storage/previews/'.$previewId.'.json');@mkdir(dirname($previewFile),0775,true);file_put_contents($previewFile,json_encode($batch,JSON_UNESCAPED_UNICODE));
      $summary=[];$total=0;foreach($batch as $item){$count=count($item['parsed']['records']??[]);$total+=$count;$summary[]=['filename'=>$item['filename'],'pages'=>$item['parsed']['pages']??0,'voters'=>$count,'success'=>!isset($item['error']),'error'=>$item['error']??null];}
      json_response(['success'=>true,'previewId'=>$previewId,'filesProcessed'=>count($batch),'totalImported'=>$total,'uniqueVoters'=>$total,'duplicates'=>0,'results'=>$summary]);
    }
    if(preg_match('#^/api/admin/preview/([a-f0-9]{32})$#',$path,$m)&&$method==='GET'){
      $file=app_root('storage/previews/'.$m[1].'.json');if(!is_file($file))json_response(['error'=>'Preview not found or expired'],404);$batch=json_decode((string)file_get_contents($file),true)?:[];$v=[];foreach($batch as $x)array_push($v,...($x['parsed']['records']??[]));json_response(['previewId'=>$m[1],'results'=>array_map(fn($x)=>['filename'=>$x['filename'],'voters'=>count($x['parsed']['records']??[]),'success'=>!isset($x['error'])],$batch),'voters'=>array_slice($v,0,100)]);
    }
    if(preg_match('#^/api/admin/preview/([a-f0-9]{32})/confirm$#',$path,$m)&&$method==='POST'){
      $file=app_root('storage/previews/'.$m[1].'.json');if(!is_file($file))json_response(['error'=>'Preview not found or expired'],404);$batch=json_decode((string)file_get_contents($file),true)?:[];$pdo=db();$pdo->beginTransaction();$count=0;
      try{foreach($batch as $item){if(isset($item['error']))continue;$p=$item['parsed'];$now=date('Y-m-d H:i:s');$st=$pdo->prepare('INSERT INTO documents(filename,stored_path,page_count,status,ocr_used,uploaded_at) VALUES(?,?,?,?,?,?)');$st->execute([$item['filename'],$item['path'],$p['pages'],'completed',$p['ocr']?1:0,$now]);$doc=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO voter_records(document_id,voter_id,serial_no,name,father_name,mother_name,birth_date,gender,occupation,address,village,ward,union_name,upazila,district,division,post_code,pdf_filename,page_number,raw_text,confidence,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');foreach($p['records'] as $r){$ins->execute([$doc,$r['voter_id']??null,$r['serial_no']??null,$r['name']??null,$r['father_name']??null,$r['mother_name']??null,$r['birth_date']??null,$r['gender']??null,$r['occupation']??null,$r['address']??null,null,null,null,null,null,null,null,$item['filename'],null,$r['raw_text']??null,$r['confidence']??0,$now]);$count++;}}$pdo->commit();@unlink($file);json_response(['success'=>true,'imported_records'=>$count,'files_processed'=>count($batch)]);}catch(Throwable $e){$pdo->rollBack();throw $e;}
    }
    if(($path==='/api/admin/export/csv'||$path==='/api/admin/export/excel')&&$method==='GET'){
      [$where,$vals]=voter_filter($_GET);$st=db()->prepare('SELECT voter_id,serial_no,name,father_name,mother_name,birth_date,gender,occupation,address,village,ward,union_name,upazila,district,division,post_code,pdf_filename,page_number FROM voter_records'.$where.' ORDER BY id DESC');$st->execute($vals);$rows=$st->fetchAll();
      if(str_ends_with($path,'/csv')){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="voter-records.csv"');echo "\xEF\xBB\xBF";$out=fopen('php://output','w');if($rows)fputcsv($out,array_keys($rows[0]));foreach($rows as $r)fputcsv($out,$r);fclose($out);exit;}
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="voter-records.xlsx"');$book=new PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();if($rows){$sheet->fromArray(array_keys($rows[0]),null,'A1');$sheet->fromArray($rows,null,'A2');}$writer=new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);$writer->save('php://output');exit;
    }
    json_response(['error'=>'Admin endpoint not found'],404);
}
