# failover-cloudflare
Script PHP para checar um site e, se estiver fora do ar, trocar o IP em um DNS no Cloudflare.

O objetivo deste script é monitorar um site e, se ele estiver fora do ar, alterar automaticamente o IP (slave) de uma ou mais entradas no Cloudflare. Quando o site voltar a responder pelo IP principal, retornar de volta para o IP principal (master).

O script assume que:
- o domínio está hospedado no Cloudflare, usando o DNS e o proxy/caching deles
- irá monitorar a homepage de um site, conectando direto no IP e enviando um Host fixo
- o site está configurado em dois webservers: um primário (master) e um de backup (slave)
- existir uma string qualquer na homepage para ser checada (o script checa a conexão e a string)
- necessita PHP 5 ou superior (não testado em versões inferiores), mas PHP 7/8 recomendado
- irá rodar em um terceiro servidor, nem no próprio master e nem no próprio slave
- este terceiro servidor tem um SMTP local (ex: Postfix) e acesso a internet

A configuração somente precisa ser feita no começo do script, alterando "$config":
- master: IP do servidor principal (ex: 1.1.1.1)
- slave: IP do servidor de backup (ex: 2.2.2.2)
- dominio: domínio a ser checado (ex: www.example.org)
- string: texto a ser procurado no corpo da homepage (ex: /html)
- entradas: lista das entradas DNS a serem alteradas, separadas por vírgula (ex: www.example.org,example.org)
- cf-email: e-mail cadastrado no Cloudflare (login)
- cf-key: chave obtida no Cloudflare (em My Profile, API Tokens, API Keys, Global API Key - ou uma gerada para um site específico)
- cf-zona: obtida no site desejado (em Overview, coluna direita, API, Zone ID)
- cf-timeout: limite de tempo para executar uma API no Cloudflare, em segundos (default: 10)
- http-timeout: limite de tempo para conectar ou receber o HTML do site sendo checado, em segundos (default: 6)
- email: um ou mais emails (separados por vírgula), para receber alertas de cada operação ou erro (sugestão: usar www.pushover.net)
- email-assunto: assunto que irá aparecer em cada status enviado por email

Códigos de retorno (útil para usar com scripts bash, exemplo: [ $? -eq 0 ] && echo "Tudo ok" || echo "Algum erro, operação ou executando slave")
- 0: tudo ok, está usando o IP master e ele está retornando a string desejada
- 1: master não respondeu, IP trocado para o slave
- 2: master voltou a responder, IP retornado para o master
- 3: tanto o master quanto o slave não responderam, ou o checador está sem acesso a internet
- 4: Cloudflare não respondeu ou deu erro ao procurar o ID do registro DNS solicitado
- 5: Cloudflare retornou erro ou não possui os dados esperados ao tentar trocar um IP
- 6: Cloudflare retornou erro ou o registro não foi alterado como pretendido
- 7: master ainda não responde, mas slave está respondendo corretamente

TODO ou DIY:
- para registros CNAME ou outros, ou sem proxy (ajustar TTL>60), editar a linha com CURLOPT_POSTFIELDS em formato json
- checagem é feita via HTTP. Para usar HTTPS, altere CURLOPT_URL em HTTPGet() - talvez necessário desativar checagem de SSL (curl)

Outros detalhes:
- utiliza um arquivo local chamado "atual.txt" para guardar o status atual (IP do servidor atualmente setado)
- se o arquivo "atual.txt" não existir ou for alterado para um IP diferente do configurado, reseta-se para o IP master
- por precaução o script checa tanto o master quanto o slave caso o IP master esteja setado, tentando descobrir se é um problema local (no terceiro servidor), ou seja, se o terceiro servidor está com problema de acesso a internet (gateway) e, neste caso, não faz nada mas envia um email que, quando a conexão for reestabelecida, deve ser enviado ao administrador sobre o problema (para checagem do terceiro servidor ou testes manuais no master e slave)
- se o master está fora do ar e foi previamente setado para o slave (com sucesso), além de continuar checando por ele (para ver quando volta ao ar), sempre checa o slave também, por precaução, e avisa se ambos sairem do ar (considerando que não é um problema do gateway do checador)
- as operações são logadas em um arquivo local "log.txt", com data e hora, além do envio do email e exibição no stdout

Instalação:
- sugestão: criar pasta /root/failover-cloudflare e colocar o .php dentro
- após configurar o "$config", execute no CRON com o caminho do script (para gravar o "atual.txt" e "log.txt" no mesmo local do .php)
- exemplo de checagem a cada 2 minutos: */2 * * * * cd /root/failover-cloudflare ; /bin/php failover-cloudflare.php
- se não quiser receber o retorno do CRON, use MAILTO="" antes da linha ou direcione a saída para nulo (acrescente: > /dev/null)
- se quiser monitorar vários sites, crie várias pastas com o .php (ou link simbólico para facilitar em futuras versões)

Sinta-se livre para alterar o script para sua necessidade.

Desenvolvido por Rogério Vitiello para uso com clientes da InWeb Internet - www.inweb.com.br
