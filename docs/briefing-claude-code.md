# Briefing Claude Code — AgendaPro BR (MVP em 14 dias)

> **Como usar este doc:** copie o conteúdo inteiro e cole na primeira mensagem de uma sessão nova do Claude Code. Ele tem tudo que precisa pra executar sem me perguntar: contexto, stack, ordem, código-base e definition of done. Partes marcadas com `<<PENDENTE>>` o fundador deve preencher antes.

---

## 1. Contexto e objetivo

Vamos forkar o projeto OSS **Easy!Appointments** (PHP + MySQL, licença GPL-3.0) e transformar em SaaS vertical brasileiro. Nome de trabalho: **AgendaPro BR** — o nome final virá do briefing de design, mas o trabalho técnico começa com o nome de trabalho.

**Público-alvo prioritário (primeira onda):** **barbeiros** — donos de barbearia com 1-3 cadeiras em bairros classe B/C. Hoje agendam por WhatsApp manual + caderno, sofrem 20-30% de no-show em horários da noite, sensíveis a preço (teto mental ~R$ 39/mês), compram por indicação em grupos de WhatsApp da categoria. Personal trainers e psicólogos ficam pra fase 2.

**Diferenciais contra o OSS genérico e contra concorrentes pagos (Calendly, Cal.com):**
- PT-BR nativo de verdade (data, hora, formato, textos, tom)
- Lembrete automático por WhatsApp 24h antes do horário
- **Pix Copia-e-Cola** dentro do próprio lembrete (reduz no-show em ~40% segundo relatos de donos de clínicas)
- Onboarding em minutos, sem precisar entender DevOps
- Preço em BRL, sem dólar

**Conjuntura de mercado (abr/2026):** Cal.com fechou o código da versão comercial. Self-hosters estão procurando alternativa ativamente. Janela curta mas real.

---

## 2. Restrições duras (leia antes de escrever qualquer linha)

- **Prazo:** MVP público em 14 dias corridos. Se não couber, corte escopo, não prazo.
- **Orçamento total:** R$ 500. Inclui domínio, infra, ferramentas. Tempo de engenharia é o recurso mais caro.
- **Licença do upstream:** GPL-3.0. Nosso fork **deve permanecer público no GitHub**. Rodar como SaaS é permitido (GPL-3.0 não tem cláusula de rede como a AGPL), mas manter fork público elimina qualquer ambiguidade jurídica. Marketing e contratos comerciais não são "o software" — esses ficam privados.
- **Validação antes de customização:** a primeira semana prioriza colocar landing + vanilla rodando + captar waitlist. Se waitlist não passar de 5 signups em 7 dias, PARE e reavalie nicho — economiza 7 dias de código jogado fora.

---

## 3. Stack e por quê

| Camada | Escolha | Razão |
|---|---|---|
| App | Easy!Appointments (PHP 8+) | Base madura, Docker pronto, i18n parcial PT-BR já existente |
| Container | Docker + docker-compose | Upstream oferece imagem oficial |
| Hospedagem | **Fly.io** região `gru` (São Paulo) | Free tier real, latência BR, HTTPS automático |
| Banco | MySQL no Fly | Compatível com o upstream, 1GB volume do free tier basta pro MVP |
| Email transacional | **Resend** | Free 3k emails/mês, API limpa, suporta domínio próprio |
| WhatsApp | **Meta Cloud API** (produção) + **Evolution API** (dev) | Cloud API é grátis nas 1k primeiras conversas/mês e não viola TOS. Evolution API é fallback pra dev, mas viola TOS — não pode virar produção |
| Pix + Split + Subcontas + Assinaturas | **Asaas** (conta PJ do fundador já existe) | Pix dinâmico via API, **split nativo** (elimina risco de subadquirência), subconta por barbeiro com walletId, cobrança recorrente da mensalidade SaaS no mesmo lugar — tudo via REST sem certificado |
| Domínio | Registro.br `.com.br` | R$ 40/ano, credibilidade local |
| Landing | Carrd.co | Free basta pro MVP; R$ 110 one-time Pro se quiser domínio próprio |
| DNS + Backup | Cloudflare (DNS grátis + R2 free 10GB) | DNS confiável + backup do MySQL |

**Custo mensal projetado depois do setup:** ~R$ 30-50. Sobra orçamento pra mídia paga se o orgânico falhar.

---

## 4. Implementação — Semana 1 (D1-D7): validar sem customizar

### D1-D2: fork + deploy vanilla

1. Fork de `github.com/alextselegidis/easyappointments` pra org `<<GITHUB_ORG>>`
2. Ajustes mínimos no fork antes do deploy:
   - Remover usuários demo do seed
   - Desabilitar registro público de admin (só convite)
   - Timezone default `America/Sao_Paulo` no `config.php`
3. Criar `fly.toml` na raiz:
   ```toml
   app = "agendapro-br"
   primary_region = "gru"
   [[vm]]
     size = "shared-cpu-1x"
     memory = "512mb"
   [[mounts]]
     source = "appointments_data"
     destination = "/var/www/html/storage"
   ```
4. Deploy: `flyctl launch --no-deploy` → revisar → `flyctl deploy`
5. Configurar subdomínio `app.agendapro.com.br` (ou equivalente que o design definir)
6. Validar HTTPS + login admin + criar 1 agendamento manual de teste

**Checkpoint D2:** URL pública funcionando, SSL válido, admin logado. Sem esse checkpoint, não avance.

### D3: landing + waitlist

1. Carrd com copy do briefing de design (headline principal + 3 bullets de valor + form de email)
2. Form → Zapier free plan → linha em Google Sheets chamada `waitlist`
3. Pixel Meta + GA4 (só pra saber origem do tráfego, não pra remarketing ainda)
4. Dispara em 3 grupos (Facebook/Telegram) seguindo o roteiro de prospecção do briefing design

### D4-D5: localização PT-BR completa

1. `grep -rn "" application/language/portuguese-br/` — listar todos os arquivos
2. Comparar com `application/language/english/` — qualquer string faltando vira TODO
3. Completar traduções (ChatGPT/Claude consegue ajudar — revisar tom, não só literal)
4. Formatos:
   - Data: `d/m/Y` (não `Y-m-d`)
   - Hora: `H:i` (24h, não AM/PM)
   - Dinheiro: `R$ 1.234,56`
5. Templates de email default em `application/views/emails/` — reescrever os 4 principais (nova reserva, lembrete, cancelamento, alteração) em PT-BR com tom do briefing design

### D6-D7: primeiros beta users + onboarding

1. PDF de 1 página: "Como usar em 10 minutos" (screenshot + seta)
2. Contatar 5 pessoas da waitlist pra beta: **R$ 19/mês vitalício pros primeiros 10** (âncora de preço + urgência real)
3. Pra cada beta, criar subdomínio manual: `barbearia-bruno.agendapro.com.br` (Fly.io permite adicionar certs via `flyctl certs add`)
4. Acompanhar uso real por 48h. Anotar: onde travam, o que perguntam, o que nunca usam.

**Checkpoint D7:** pelo menos 3 clientes usando de verdade OU a decisão consciente de continuar só com o sinal que tem. Se tiver 0 clientes e <5 na waitlist, **pare e reavalie nicho** — não vá pra semana 2.

---

## 5. Implementação — Semana 2 (D8-D14): WhatsApp + Pix + launch

### D8-D10: lembrete WhatsApp 24h antes

**Arquitetura simples:**
- Cron job de hora em hora
- Query: agendamentos entre `now + 23h` e `now + 24h` que ainda não receberam lembrete
- Pra cada um: dispara mensagem Meta Cloud API + marca `reminder_sent_at`

**Código de referência** (`application/helpers/whatsapp_helper.php`):

```php
function send_whatsapp_reminder($appointment) {
    $template_name = 'lembrete_agendamento'; // cadastrado no BM da Meta
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => normalize_phone_br($appointment->customer_phone),
        'type' => 'template',
        'template' => [
            'name' => $template_name,
            'language' => ['code' => 'pt_BR'],
            'components' => [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $appointment->customer_first_name],
                    ['type' => 'text', 'text' => $appointment->service_name],
                    ['type' => 'text', 'text' => format_br_datetime($appointment->start)],
                    ['type' => 'text', 'text' => $appointment->pix_copia_cola ?? ''],
                ],
            ]],
        ],
    ];
    return meta_api_post("/{$phone_number_id}/messages", $payload);
}
```

**Template a cadastrar no WhatsApp Business Manager** (aprovação leva 24-48h — **faça no D1** pra não travar aqui):
```
Oi {{1}}! Lembrando do seu horário: {{2}} amanhã às {{3}}.

Pra adiantar o pagamento e garantir seu horário, use o Pix Copia-e-Cola:
{{4}}

Se precisar remarcar, responda esta mensagem.
```

**Fallback Evolution API** pra dev/testes (não pra produção real):
- VPS Hostinger R$ 12/mês
- Docker: `atendai/evolution-api:latest`
- Endpoint `/message/sendText/{instance}` com body `{number, textMessage}`

### D11-D12: Pix Copia-e-Cola + Split + Subcontas (via Asaas)

**Modelo de operação (definitivo, sem caveat regulatório):**
- **Conta principal Asaas:** já existente, do fundador (CNPJ da empresa SaaS)
- **Cada barbeiro vira uma subconta Asaas**, criada via API quando ele se cadastra. Asaas retorna `apiKey` da subconta + `walletId` (necessário pro split)
- **Cobranças do cliente final do barbeiro** (ex: corte de cabelo R$ 50) saem com **split de pagamento** definido na criação da cobrança: valor líquido vai pro barbeiro, fatia pré-definida vai pro fundador (mensalidade SaaS embutida ou comissão)
- **Mensalidade SaaS recorrente** (R$ 19/mês ou R$ 39/mês) é uma **assinatura Asaas** criada na conta principal apontando pra subconta do barbeiro como pagador — débito automático mensal via Pix
- **Zero risco regulatório:** Asaas é a instituição autorizada pelo BACEN. Fundador não é facilitador de pagamento, não opera float indevido

**Setup Asaas — fundador faz UMA vez no D11:**
1. Conta PJ Asaas já existe ✅
2. Painel Asaas → Minha Conta → Integração → Gerar API Key (sandbox primeiro, depois produção)
3. Configurar webhook: `https://app.[dominio].com.br/webhooks/asaas` com eventos `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED`, `PAYMENT_OVERDUE`
4. Cadastrar chave Pix da empresa (CNPJ ou aleatória) — opcional pro MVP, Asaas gera Pix dinâmico via instituição parceira mesmo sem chave registrada
5. Testar criação de subconta no sandbox: `POST /v3/accounts` com dados de teste

**Código de referência — cobrança Pix com split** (`application/helpers/asaas_helper.php`):

```php
function asaas_request($method, $path, $body = null) {
    $ch = curl_init("https://api.asaas.com/v3{$path}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'access_token: ' . env('ASAAS_API_KEY'),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body ? json_encode($body) : null,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function gerar_pix_agendamento($appointment, $valor_reais, $barbeiro_walletId, $fee_reais = 2.00) {
    // 1. Criar (ou recuperar) cliente Asaas pro consumidor final
    $customer = asaas_request('POST', '/customers', [
        'name' => $appointment->customer_name,
        'cpfCnpj' => $appointment->customer_cpf, // opcional
        'mobilePhone' => $appointment->customer_phone,
    ]);
    
    // 2. Criar cobrança Pix com split
    $payment = asaas_request('POST', '/payments', [
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => $valor_reais,
        'dueDate' => date('Y-m-d', strtotime('+1 day')),
        'description' => "Agendamento #{$appointment->id} — {$appointment->service_name}",
        'externalReference' => "appt_{$appointment->id}",
        // Split: o que vai pra subconta do barbeiro (resto fica na conta principal/fundador)
        'split' => [[
            'walletId' => $barbeiro_walletId,
            'fixedValue' => $valor_reais - $fee_reais,
        ]],
    ]);
    
    // 3. Recuperar Pix Copia-e-Cola
    $pix = asaas_request('GET', "/payments/{$payment['id']}/pixQrCode");
    
    // 4. Persistir no appointment
    update_appointment($appointment->id, [
        'asaas_payment_id' => $payment['id'],
        'pix_copia_cola' => $pix['payload'],
        'pix_qr_base64' => $pix['encodedImage'],
        'pix_expires_at' => $pix['expirationDate'],
    ]);
    
    return $pix['payload'];
}
```

**Código de referência — criação de subconta do barbeiro** (`application/helpers/asaas_helper.php`):

```php
function criar_subconta_barbeiro($barbeiro) {
    $response = asaas_request('POST', '/accounts', [
        'name' => $barbeiro->business_name,
        'email' => $barbeiro->email,
        'cpfCnpj' => $barbeiro->cpf_cnpj,
        'companyType' => 'MEI', // padrão pro nicho
        'mobilePhone' => $barbeiro->phone,
        'address' => $barbeiro->address,
        'addressNumber' => $barbeiro->address_number,
        'province' => $barbeiro->neighborhood,
        'postalCode' => $barbeiro->cep,
    ]);
    
    // ⚠️ apiKey só é retornada UMA vez — armazenar imediatamente, criptografada
    save_barbeiro_credentials($barbeiro->id, [
        'asaas_account_id' => $response['id'],
        'asaas_api_key' => encrypt($response['apiKey']),
        'asaas_wallet_id' => $response['walletId'],
    ]);
    
    return $response;
}
```

**Webhook handler** (`application/controllers/Webhooks.php`):

```php
public function asaas() {
    $event = json_decode(file_get_contents('php://input'), true);
    
    switch ($event['event']) {
        case 'PAYMENT_RECEIVED':
        case 'PAYMENT_CONFIRMED':
            $payment_id = $event['payment']['id'];
            $external_ref = $event['payment']['externalReference']; // appt_123
            
            if (preg_match('/^appt_(\d+)$/', $external_ref, $m)) {
                update_appointment($m[1], ['payment_status' => 'paid']);
                // dispara confirmação por WhatsApp pro cliente
                send_whatsapp_payment_confirmation($m[1]);
            }
            break;
            
        case 'PAYMENT_OVERDUE':
            // notificar barbeiro que cliente não pagou
            break;
    }
    
    http_response_code(200);
}
```

**Edge cases a cobrir:**
- Serviço gratuito (psicóloga que cobra só no presencial — fase 2) → não cria cobrança, template WhatsApp usa fallback sem Pix
- Barbeiro sem CPF/CNPJ formal cadastrado → bloquear criação da subconta no UI, exigir CPF mínimo
- Webhook recebido em duplicidade → idempotência via `asaas_payment_id` único na tabela
- Cobrança expirada antes do pagamento → criar nova automaticamente quando cliente pedir (botão "atualizar Pix" no link público)

**Mensalidade SaaS recorrente do barbeiro** (criar quando barbeiro vira pagante, não no MVP inicial):

```php
function criar_assinatura_saas($barbeiro, $valor_mensal = 19.00) {
    return asaas_request('POST', '/subscriptions', [
        'customer' => $barbeiro->asaas_customer_id_principal, // criar antes
        'billingType' => 'PIX',
        'value' => $valor_mensal,
        'nextDueDate' => date('Y-m-d', strtotime('+30 days')),
        'cycle' => 'MONTHLY',
        'description' => 'Mensalidade AgendaPro BR',
    ]);
}
```

### D13: hardening

- [ ] Rate limit no endpoint público de booking: 10 req/min por IP (CodeIgniter tem middleware ou NGINX faz)
- [ ] Backup diário do MySQL pra Cloudflare R2: cron `mysqldump | gzip | rclone copy - r2:backups/`
- [ ] Monitoring: BetterStack free tier monitora `app.agendapro.com.br/health` a cada 3 min
- [ ] Logs centralizados: Fly.io já agrega; configurar alerta Slack/email em 5xx
- [ ] Remover menções "Easy!Appointments" do UI visível (mantém em `/licenses` e no footer com link, conforme exige GPL-3.0)

### D14: launch público

- [ ] Landing troca "entre na waitlist" por "começar agora" com CTA pra WhatsApp do fundador
- [ ] Fork público com README em PT-BR no GitHub
- [ ] Post no LinkedIn + HN Show + r/SaaS + r/empreendedorismo anunciando
- [ ] Primeiro cliente pagante (não beta) confirmado

---

## 6. Definition of Done

MVP está pronto quando **todos** os itens abaixo são verdade:

- [ ] 3 clientes beta usando em produção há pelo menos 7 dias sem bug crítico
- [ ] Lembrete WhatsApp sai 24h antes em 95%+ dos agendamentos (medir via dashboard simples)
- [ ] Pix Copia-e-Cola gerado em 100% dos lembretes de serviços pagos
- [ ] Push em `main` → deploy automático em Fly.io (via GitHub Actions)
- [ ] Backup diário executando há 3+ dias seguidos com sucesso
- [ ] 1 cliente pagante não-beta confirmado e rodando
- [ ] Fork público acessível em `github.com/<<GITHUB_ORG>>/agendapro-br`

---

## 7. Anti-goals (NÃO fazer no MVP)

Qualquer item abaixo que entrar no escopo vai estourar o prazo. Anote pra fase 2:

- Multi-tenancy automatizado (cada cliente é uma instância manual; manual escala até ~50 clientes)
- App mobile nativo (o PWA do Easy!Appointments resolve)
- Onboarding self-service com Stripe/Mercado Pago (a primeira onda é venda consultiva)
- Subdomínio criado por cliente via UI (manual via `flyctl certs add` é ok pra 10 clientes)
- Dashboard de analytics próprio (o upstream já tem básico)
- Emissão de NFe ou recibo CRP (fase 2, nicho-específico)
- Integração com Google Calendar 2-way (já vem do upstream — não customizar)
- Notificações por SMS (caro + supérfluo quando WhatsApp funciona)
- Temas customizados por cliente (white-label pesado vem depois)

---

## 8. Credenciais que o fundador deve ter antes

**Antes do D1:**
- [ ] Conta Fly.io (cartão válido mesmo no free)
- [ ] Conta Cloudflare (DNS + R2)
- [ ] Conta Registro.br + domínio comprado: `<<DOMINIO>>.com.br` (depois do nome definido pelo design)
- [ ] Conta GitHub + organização criada: `<<GITHUB_ORG>>`
- [ ] Conta Carrd
- [ ] Conta Resend + domínio verificado (DKIM/SPF)
- ✅ **Conta Asaas PJ já existe** — só gerar API key sandbox no painel quando chegar no D11

**Antes do D8 (WhatsApp):** — fazer paralelo à semana 1!
- [ ] WhatsApp Business Account verificado no Meta Business Manager
  - Processo: Business Manager → Verificação de negócio → CNPJ + docs → 1-3 dias
  - Cloud API Phone Number ID + Permanent Access Token
  - **Template `lembrete_agendamento` submetido pra aprovação no D1** (leva 24-48h)
- [ ] Backup: VPS Hostinger R$ 12/mês com Evolution API rodando (pra dev)

**Antes do D11 (Pix + Split + Subcontas):** — quase tudo já está pronto, é só gerar a API key
- ✅ Conta PJ Asaas já existe
- [ ] API Key sandbox gerada (Painel Asaas → Minha Conta → Integração → Gerar API Key)
- [ ] API Key produção gerada (mesma rota, ambiente produção)
- [ ] Webhook configurado em `https://app.[dominio].com.br/webhooks/asaas` com eventos `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED`, `PAYMENT_OVERDUE`
- [ ] (Opcional) Chave Pix da empresa cadastrada — Asaas gera Pix dinâmico via parceira mesmo sem chave registrada, então **não bloqueia o D11**

**Pontos de atenção pra fase 2 (não bloqueantes pro MVP):**
- **Avaliação regulatória:** Asaas aplica checklist quando passa de certos limites de subcontas/volume. Pra 10-30 barbeiros não trava. Quando passar de 50, preparar documentação dos barbeiros (RG/CNPJ/comprovante de endereço) pra desbloquear novas subcontas
- **Notas fiscais:** cada barbeiro emite NF do serviço dele; o fundador emite NF do SaaS sobre a mensalidade que recebe. Asaas tem emissão automatizada de NFSe — ativar quando volume justificar

---

## 9. Compliance GPL-3.0 (não ignorar)

| O que | Pode? | Observação |
|---|---|---|
| Rodar como SaaS sem publicar modificações | ✅ | GPL-3.0 não tem cláusula de rede (isso é AGPL) |
| Manter fork público mesmo assim | ✅ | Recomendado — zera ambiguidade e vira marketing |
| Cobrar pelo hosted | ✅ | GPL permite venda |
| Manter privado nosso Terms/Privacy/marketing | ✅ | Não são "o software" |
| Relicenciar pra MIT ou fechar | ❌ | Código derivado mantém GPL-3.0 |
| Distribuir binário sem source | ❌ | Se distribuir, source junto |

**Na prática:** fork público + SaaS cobrado = 100% legal. É o mesmo modelo de GitLab, Plausible, Chatwoot.

---

## 10. Perguntas bloqueantes pro fundador (responder antes do D1)

**Já decidido / resolvido (não perguntar de novo):**
- ✅ Nicho prioritário: **barbeiros**
- ✅ Modelo Pix: **Asaas com split + subcontas + assinaturas** (PJ do fundador já existe; zero risco regulatório)
- ✅ Nome do produto: virá do briefing de design (Claude Code usa "AgendaPro BR" como placeholder até o design entregar — daí faz find-and-replace global no fork)

**Ainda pendentes — responder antes do D1:**
1. Domínio `.com.br` comprado? Qual é? (esperar nome final do design antes de comprar)
2. Organização GitHub criada? Qual é?
3. Cidade/região do fundador (pra primeiros posts em grupos locais de barbeiros)?

---

**Este briefing é o contrato de escopo.** Qualquer mudança após o D1 só com renegociação explícita do prazo — não do orçamento, que é fixo.
