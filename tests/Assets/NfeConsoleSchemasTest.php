<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);
$dir=$root.'/Schemas/NFe-1.5.0.489-console/';
$manifest=json_decode(file_get_contents($dir.'manifest.json'),true,512,JSON_THROW_ON_ERROR);
foreach($manifest['sha256'] as $file=>$hash){
    if(hash_file('sha256',$dir.$file)!==$hash){fwrite(STDERR,"Schema checksum mismatch: $file\n");exit(1);}
}
$previous=libxml_use_internal_errors(true);
try {
    foreach(['12ABC34501DE35','06013812000158'] as $cnpj){
        $key='322610'.$cnpj.'550030001972521191972527';
        $xml='<consSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><xServ>CONSULTAR</xServ><chNFe>'.$key.'</chNFe></consSitNFe>';
        $d=new DOMDocument();$d->loadXML($xml);
        if(!$d->schemaValidate($dir.'consSitNFe_v4.00.xsd')){fwrite(STDERR,"Key schema validation failed: $cnpj\n");exit(1);}
        $xml='<ConsCad xmlns="http://www.portalfiscal.inf.br/nfe" versao="2.00"><infCons><xServ>CONS-CAD</xServ><UF>SP</UF><CNPJ>'.$cnpj.'</CNPJ></infCons></ConsCad>';
        $d->loadXML($xml);
        if(!$d->schemaValidate($dir.'consCad_v2.00.xsd')){fwrite(STDERR,"CNPJ schema validation failed: $cnpj\n");exit(1);}
    }
}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
echo "OK\n";
