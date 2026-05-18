# Sistema Tôa Tôa Moda Festa

Sistema de gestão integrada para lojas de moda festa, abrangendo controle de estoque, PDV, cadastro de clientes e geração de catálogos digitais.

## 🎨 Identidade Visual (Paleta de Cores)

Azul Profundo (Fundo): #001D3D

É um azul marinho muito escuro, quase preto, que transmite elegância e sofisticação (ideal para moda festa).

Amarelo Ouro (Detalhes e Bordas): #FFD700

Um dourado vibrante usado nas bordas e nos textos de destaque. Traz o contraste necessário para a leitura.

Verde Esmeralda (Símbolo Central): #2D8A4E

Aquele verde que forma o mapa ou a base atrás do nome. É uma cor que remete ao estado de Mato Grosso.

Vermelho Vivo (Círculo Superior): #E60000

Usado no detalhe do ponto/círculo acima da logo. Serve como um ponto de atenção.

## 🛠️ Tecnologias e Arquitetura

- **Frontend:** PHP 8.x + Bootstrap 5.3 (Interface Administrativa e PDV).
- **Backend de Dados:** Node.js API (Hospedado no Render) conectando ao **Supabase (PostgreSQL)**.
- **Integrações Externas:** API ViaCEP para preenchimento automático de endereços.
- **Segurança:** Comunicação PHP-Node validada via `x-api-key`.

## ⚙️ Configuração de Ambiente

1. No arquivo `db.php`, certifique-se de que a variável `$api_key` corresponde à chave definida no servidor Node.js.
2. Para rodar o servidor localmente:
   ```bash
   php -S localhost:8000
   ```

## 📋 Entrevista e Definição de Escopo

1. Com o sistema, o que pretende?

R: Controle de estoque (entrada e saída) e controle financeiro.

2. Com o sistema, qual tipo de gestão pretende realizar?

R: Gestão financeira (entrada de dinheiro, cartão, débito e crédito); Criar catálogo no WhatsApp; Verificar um custo zero; Catálogo web.

3. Daqui a 5 anos, onde você vê a loja?

R: Ficar com a loja e conseguir atender o cliente melhor. Transformar em site de vendas.

4. A loja em médio, longo e curto prazo, como entende e planeja para a loja?

R: Curto Prazo: Formalizar o cliente e pesquisa; Entrada de histórico de aluguel e venda. Médio Prazo: Tudo automatizado. Longo Prazo: (Em branco).

5. Pretende escalar para abertura de novas lojas?

R: Não, somente 1 loja, mas melhor entendê-la.

6. O seu público-alvo, como você entende para conseguir a integração com os produtos e o público-alvo?

R: (Resposta implícita nas melhorias de atendimento e catálogo).

7. Pretende criar um site real da loja para que os clientes tenham melhor visibilidade da loja?

R: Sim, criar um bom catálogo para aluguel e venda.

8. Integração entre vendedor, cliente e APP hoje?

R: Integração melhor para o atendimento e conseguir enviar o catálogo.

## 🚀 Requisitos do Sistema

### Requisitos Funcionais (RF)

- **RF01 - Gestão de Estoque:** Cadastro, edição, visualização e **exclusão definitiva** de produtos (vestidos).
- **RF02 - Gestão de Aluguéis:** Registro de histórico de locação com controle de datas.
- **RF03 - Ponto de Venda (PDV):** Interface para registro de vendas rápidas com baixa automática de estoque.
- **RF04 - Controle Financeiro:** Registro de fluxos por forma de pagamento (Dinheiro, PIX, Cartões).
- **RF05 - Gestão de Clientes:** Cadastro completo com busca de CEP e histórico de interações.
- **RF06 - Vitrine Digital:** Geração de links personalizados de produtos selecionados para envio via WhatsApp.
- **RF07 - Relatórios:** Extração de dados sobre desempenho de vendas e disponibilidade.

### Requisitos Não Funcionais (RNF)

- **RNF01 - Usabilidade:** Interface adaptada para operação rápida em notebooks de balcão.
- **RNF02 - Disponibilidade:** O catálogo público deve permanecer online independentemente do sistema local.
- **RNF03 - Confiabilidade:** Transações de venda com rollback em caso de falha de conexão.

## 📂 Comandos Úteis (Git/Deploy)

```bash
git add .
git commit -m "Descricao da alteracao"
git branch -m master main
git push origin main

```

---
*Tôa Tôa Moda Festa - Cuiabá/MT*