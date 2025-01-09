<?php
/////////////////////////
//Failover via CloudFlare
//Versão 1.2 - 09/01/2025
/////////////////////////
//Por Rogério Vitiello - www.arvy.com.br
//Criado para a InWeb Internet - www.inweb.com.br
//Uso livre, altere para sua necessidade
//https://github.com/ArvyRogerio/failover-cloudflare
/////////////////////////

//Altere somente aqui!
$configs=array(
        'master'=>'1.1.1.1',
        'slave'=>'2.2.2.2',
        'dominio'=>'www.example.org',
        'string'=>'/html',
        'entradas'=>'example.org,www.example.org',
        'cf-email' => 'seuemail-cf@example.org',
        'cf-key' => '-key-cloudflare-letras-e-numeros-',
        'cf-zona'=>'-zone-id-do-site-letras-e-numeros-',
        'cf-timeout' => 10,
        'http-timeout' => 6,
        'email'=>'seuemail@suaempresa.com.br,idpushover@pomail.net',
        'email-assunto'=>'[Failover CF] Assunto do Email'
);

////////////////

if (!file_exists($_SERVER['PWD'].'/atual.txt')) file_put_contents($_SERVER['PWD'].'/atual.txt',$configs['master']);

$Atual=file_get_contents($_SERVER['PWD'].'/atual.txt');

//Proteção, provavelmente por troca de IPs
if ($Atual!=$configs['master'] and $Atual!=$configs['slave']) {
   file_put_contents($_SERVER['PWD'].'/atual.txt',$configs['master']);
   $Atual=$configs['master'];
}

//Checa Master
$Retorno=HTTPGet($configs['master']);

//Se chegou aqui, tudo ok, não faz nada: está no master e o ip já está no master
if (strpos($Retorno,$configs['string'])>0 and $Atual==$configs['master']) exit(0);

//Se deu erro e está no master, aponta para o slave
if (strpos($Retorno,$configs['string'])===false and $Atual==$configs['master']) {

   //Mas antes checa o slave, porque pode ser problema na internet do checador, e tenta avisar se possivel (queue email, ao voltar)
   $Retorno=HTTPGet($configs['slave']);

   //Muito provavelmente este servidor está sem internet (gw)...
   //Não faz nada para não dar confusão e se oscilou avisará o admin para checar manualmente
   if (strpos($Retorno,$configs['string'])===false) {
      Info('Master e slave estão fora do ar? Ou servidor de checagem sem internet em '.date('d/m/Y H:i:s').'!'); //Tenta avisar
      exit(3);
   }

   //Slave respondeu, então não é problema no checador: altera
   foreach (explode(',',$configs['entradas']) as $e) Alterar($e,$configs['slave']);

   file_put_contents($_SERVER['PWD'].'/atual.txt',$configs['slave']);
   exit(1);

}

//Se master respondeu (voltou) e está no slave, volta para o master
if (strpos($Retorno,$configs['string'])>0 and $Atual==$configs['slave']) {
   foreach (explode(',',$configs['entradas']) as $e) Alterar($e,$configs['master']);
   file_put_contents($_SERVER['PWD'].'/atual.txt',$configs['master']);
   exit(2);
}

//Ainda master fora do ar e já está no slave, por precaução faz um teste no slave
if (strpos($Retorno,$configs['string'])===false and $Atual==$configs['slave']) {

   //Checa Slave
   $Retorno=HTTPGet($configs['slave']);

   //Checa o slave, porque pode ser problema na internet do checador, e tenta avisar se possivel (queue email, ao voltar)
   //Se não for a internet do checador, é um grande problema, ambos sairam do ar! :(
   if (strpos($Retorno,$configs['string'])===false) {
      Info('Master e slave estão fora do ar? Ou servidor de checagem sem internet em '.date('d/m/Y H:i:s').'!'); //Tenta avisar
      exit(3);
   }

   //Se chegou aqui, slave respondeu corretamente, aguardando master voltar
   exit(7);

}

/////////////
// Funções //
/////////////

function HTTPGet($IP) {

   global $configs;

   $curl=curl_init();

   curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_CONNECTTIMEOUT => $configs['http-timeout'],
        CURLOPT_TIMEOUT => $configs['http-timeout'],
        CURLOPT_HTTPHEADER => array("Host: ".$configs['dominio'],"Content-Type: text/html"),
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_URL => 'https://'.$IP
   ));

   $resp=curl_exec($curl);
   curl_close($curl);

   return $resp;

}

function Alterar($Registro,$IP) {

   global $configs;

   $curl=curl_init();

   curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_CONNECTTIMEOUT => $configs['cf-timeout'],
        CURLOPT_TIMEOUT => $configs['cf-timeout'],
        CURLOPT_HTTPHEADER => array("X-Auth-Email: ".$configs['cf-email'],"X-Auth-Key: ".$configs['cf-key'],"Content-Type: application/json"),
        CURLOPT_URL => 'https://api.cloudflare.com/client/v4/zones/'.$configs['cf-zona'].'/dns_records?name='.$Registro.'&type=A'
   ));

   $resp=curl_exec($curl);
   curl_close($curl);

   $resp=substr($resp,strpos($resp,'{'));
   $resp=json_decode($resp,true);

   if (!isset($resp['result'][0]['id'])) {
      Info('Sem retorno na consulta do registro');
      exit(4);
   }

   $resp=$resp['result'][0]['id'];

   $curl=curl_init();

   curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_CONNECTTIMEOUT => $configs['cf-timeout'],
        CURLOPT_TIMEOUT => $configs['cf-timeout'],
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_HTTPHEADER => array("X-Auth-Email: ".$configs['cf-email'],"X-Auth-Key: ".$configs['cf-key'],"Content-Type: application/json"),
        CURLOPT_URL => 'https://api.cloudflare.com/client/v4/zones/'.$configs['cf-zona'].'/dns_records/'.$resp,
        CURLOPT_POSTFIELDS => '{"type":"A","name":"'.$Registro.'","content":"'.$IP.'","ttl":1,"proxied":true}'
   ));

   $resp=curl_exec($curl);
   curl_close($curl);

   $resp=substr($resp,strpos($resp,'{'));
   $resp=json_decode($resp,true);

   if (!isset($resp['result']['id']) or !isset($resp['success'])) {
      Info('Erro no retorno CF, sem os campos de retorno');
      exit(5);
   }

   if ($resp['result']['content']!=$IP or $resp['success']!='1') {
      Info('Erro no retorno CF, IP nao alterado ou erro ao alterar');
      exit(6);
   }

   //Tudo ok, alterou a entrada DNS
   Info('Alterada a entrada '.$Registro.' para o IP '.$IP);
   
}

function Info($T) {
   global $configs;
   foreach (explode(',',$configs['email']) as $e) mail($e,$configs['email-assunto'],$T);
   error_log(date('Y-m-d H:i:s').': '.$T."\n",3,$_SERVER['PWD'].'/log.txt');
   echo $T."\n";
}
?>
