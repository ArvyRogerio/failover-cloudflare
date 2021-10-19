# failover-cloudflare
Script PHP para checar um site e, se estiver fora do ar, trocar o IP em um DNS no Cloudflare

O objetivo deste script é monitorar um site e, se ele estiver fora do ar, alterar automaticamente o IP (slave) de uma ou mais entradas no Cloudflare. Quando o site voltar a responder pelo IP principal, retornar de volta para o IP principal (master).

O script assume que:
- o domínio está hospedado no Cloudflare, usando o DNS e o proxy/caching deles
- irá monitorar a homepage de um site, conectando direto no IP e enviando um Host fixo
- o site está configurado em dois webservers: um primário (master) e um de backup (slave)
- há uma string qualquer na homepage para ser checada (o script checa a conexão e a string)

 
