<?php
$ps = new \Cupons\Core\Import\Profitshare(); $r = new ReflectionMethod($ps, 'request'); $r->setAccessible(true);
for ($i=0;$i<8;$i++){ $d=$r->invoke($ps,'GET','affiliate-advertisers/',[]); $w=0; foreach($d['result'] as $a) if(isset($a['commissions']['affiliate_statuses'])) $w++; echo date('H:i:s')." call $i: cu status $w/".count($d['result'])." | eMAG commissions: ".json_encode($d['result']['35']['commissions'] ?? null)."\n"; sleep(3); }
