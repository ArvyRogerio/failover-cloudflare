# failover-cloudflare
Script PHP para checar um site e, se estiver fora do ar, trocar o IP em um DNS no Cloudflare.

O objetivo deste script é monitorar um site e, se ele estiver fora do ar, alterar automaticamente o IP (slave) de uma ou mais entradas no Cloudflare. Quando o site voltar a responder pelo IP principal, retornar de volta para o IP principal (master).

O script assume que:
- o domínio está hospedado no Cloudflare, usando o DNS e o proxy/caching deles
- irá monitorar a homepage de um site, conectando direto no IP e enviando um Host fixo
- o site está configurado em dois webservers: um primário (master) e um de backup (slave)
- há uma string qualquer na homepage para ser checada (o script checa a conexão e a string)
- necessita PHP 5 ou superior (não testado em versões inferiores). PHP 7/8 recomendado.

A configuração somente precisa ser feita no começo do script, alterando "$config":
- master: IP do servidor principal (ex: 1.1.1.1)
- slave: IP do servidor de backup (ex: 2.2.2.2)
- dominio: domínio a ser checado (ex: www.example.org)
- string: texto a ser procurado no corpo da homepage (ex: </html>)
- entradas: lista das entradas DNS a serem alteradas, separadas por vírgula (ex: www.example,org,example.org)
- cf-email: e-mail cadastrado no Cloudflare (login)
- cf-key: chave obtida no Cloudflare (em My Profile, API Tokens, API Keys, Global API Key - ou uma gerada para um site específico)
- cf-zona: obtida no site desejado (em Overview, coluna direita, API, Zone ID)
- cf-timeout: limite de tempo para executar uma API no Cloudflare, em segundos (default: 10)
- http-timeout: limite de tempo para conectar ou receber o HTML do site sendo checado, em segundos (default: 6)
- email: um ou mais emails (separados por vírgula), para receber alertas de cada operação ou erro (sugestão: usar www.pushover.net)
- email-assunto: assunto que irá aparecer em cada status enviado por email

