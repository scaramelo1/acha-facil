# Acha Fácil!

Achar profissionais de São João da Boa Vista. Plano e passos: [docs/00-passos.md](docs/00-passos.md).

## App (PC)

```powershell
npm install
npx tsc --noEmit
npx expo lint
npx expo start --android   # com o emulador do Android Studio aberto
```

## Servidor (VPS, como root)

```bash
bash servidor/instalar.sh              # cria/atualiza banco, tabelas e publicação
bash servidor/instalar.sh --exemplos   # recoloca os 10 profissionais fictícios
```

API: https://fsadesigners.com.br/acha-facil/api/inicio.php
