<?php
return array (
  'provider' => 'ollama',
  'use_ollama' => true,
  'ollama_url' => 'http://localhost:11434/v1/chat/completions',
  'ollama_model' => 'qwen3.5:2b',
  'openai_key' => '',
  'openai_model' => 'gpt-4o-mini',
  'openrouter_key' => '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',
  'openrouter_model' => 'openrouter/free',
  'custom_url' => 'https://apifreellm.com/api/v1/chat',
  'custom_key' => '',
  'custom_model' => '',
  'assistant_name' => 'RADNEX',
  'system_prompt' => 'Tu es RADNEX, un assistant IA intelligent spécialisé en finance et gestion d\'entreprise. Tu as accès à Internet pour rechercher les informations les plus récentes. Sois concis, précis, professionnel et utilise le français. Pour les questions financières, fournis des analyses claires avec des exemples concrets. Cite toujours tes sources quand tu utilises la recherche web.',
  'language' => 'fr',
  'web_search_enabled' => true,
  'timeout' => 60,
);
