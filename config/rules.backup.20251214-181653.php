<?php
/**
 * Reglas editables (archivo).
 * - Este archivo es "la fuente" para mostrar/ocultar campos en formularios y validaciones básicas.
 * - Puedes editarlo manualmente en cPanel (File Manager) sin tocar DB.
 *
 * IMPORTANTE:
 * - Los 'type_code' vienen de la tabla article_types.code
 * - Los 'division_code' vienen de la tabla divisions.code (si existe) o usamos divisions.id/prefix. (por ahora UI)
 */
return [
  'articles' => [
    // Campos visibles/obligatorios por tipo de artículo (article_types.code)
    'type_field_rules' => [
      // Ajusta estos códigos a los que tengas en tu tabla article_types
      // Ejemplos típicos: 'cajetin','soporte','fruto','placa','cubretecla'
      'cajetin' => ['show' => ['modules'], 'hide' => ['requires_cover']],
      'soporte' => ['show' => ['modules'], 'hide' => ['requires_cover']],
      'placa'   => ['show' => ['modules'], 'hide' => ['requires_cover']], // placa no requiere cubretecla
      'cubretecla' => ['show' => [],      'hide' => ['requires_cover']],
      'fruto'   => ['show' => ['modules'], 'hide' => ['requires_cover']],
      'mecanismo' => ['show' => ['modules','requires_cover'], 'hide' => []],
      'router' => ['show' => [], 'hide' => ['modules','requires_cover']],
      'AP' => ['show' => [], 'hide' => ['modules','requires_cover']],
      'Switche' => ['show' => [], 'hide' => ['modules','requires_cover']],
    ],
    // Fallback si el type_code no está arriba
    'default' => ['show' => [], 'hide' => []],
  ],

  'points' => [
    // Placeholder para reglas por división (electrico/red/cctv/etc)
    // Ejemplo de estructura que vamos a usar luego:
    // 'ELECTRICO' => [
    //   'requires' => ['support','fruits'],
    //   'optional' => ['plate'],
    //   'auto_fill_blanks' => true,
    // ],
  ],
];
