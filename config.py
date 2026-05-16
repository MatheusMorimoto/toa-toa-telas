import os

class Config:
    """Configurações básicas do Flask"""
    SECRET_KEY = os.environ.get('SECRET_KEY') or 'uma-chave-muito-segura-e-secreta'
    DEBUG = True
    # Você pode adicionar outras configurações aqui (Banco de dados, etc.)