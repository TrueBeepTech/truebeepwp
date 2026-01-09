# AGENT Instructions for WordPress Project

## Goal

The primary goal is to maintain and extend a custom WordPress theme or plugin, following WordPress Coding Standards and best practices for security, maintainability, and proper role/capability usage.

## Best Practices

* **WordPress Coding Standards:**  
  All PHP, JS, CSS, and HTML code must strictly adhere to the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

* **User Roles and Capabilities:**  
  Always check user capabilities before displaying content or executing actions. Use [`current_user_can()`](https://developer.wordpress.org/reference/functions/current_user_can/) for all permission checks.

* **Security, Sanitization, and Validation:**  
  - Sanitize all incoming data using relevant functions (`sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, etc.).  
  - Always escape data on output with the proper escaping function: `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_url()`, `esc_js()`, and others, as relevant to the context.  
  - Never use `echo` for raw user or variable output; instead, wrap all output with escaping and/or translation functions.  
  - Use `wp_kses()` with allowed HTML arrays when outputting HTML from user or third-party input.

* **API/HTTP Requests:**  
  Use the built-in [wp_remote_get()](https://developer.wordpress.org/reference/functions/wp_remote_get/) and [wp_remote_post()](https://developer.wordpress.org/reference/functions/wp_remote_post/) functions for all external HTTP requests. Always validate and sanitize external data before use.

  **Example of safe HTTP request:**
  ```php
  $response = wp_remote_get( esc_url_raw( $url ) );
  if ( is_wp_error( $response ) ) {
      // Handle error safely, e.g., use esc_html__() for messages
      echo esc_html__( 'There was an error with the remote request.', 'your-text-domain' );
  } else {
      $body = wp_remote_retrieve_body( $response );
      // Sanitize and process $body as appropriate before output
  }
  ```

* **Nonces and Form Security:**  
  Use `wp_nonce_field()`, `wp_verify_nonce()`, and related functions to protect all forms and custom actions from CSRF vulnerabilities.

* **Theme/Plugin Structure:**  
  Work within your theme/plugin directory only (e.g., `wp-content/themes/your-theme/` or `wp-content/plugins/your-plugin/`).  
  **Do not edit WordPress core files.**

* **Requirements Discussion:**  
  Before coding, discuss requirements and propose a technical approach for clarity and maintainability.

* **Context Sharing:**  
  Use the `@file` or `@folder` tags in chat to specify what part of the code you are working with.

## Prohibited Actions

* Never modify or overwrite any core WordPress files.
* Do not use inline JavaScript or CSS; always enqueue scripts and styles properly.
* Never expose API keys or credentials in the codebase; always use environment variables and secure configuration.
* Never output unescaped variables; always escape and translate, and prefer WordPress helper functions over direct output.

## API Keys

* API keys or sensitive credentials must **never** be hardcoded or committed. Store them in secure environment variables and access via safe methods (`getenv()`, etc.).

## Summary: WordPress Coding & Security Checklist

| Use Case                       | Recommended Function(s)                      |
|--------------------------------|----------------------------------------------|
| Sanitize Text Input            | `sanitize_text_field()`                      |
| Sanitize Email                 | `sanitize_email()`                           |
| Escape Attribute Output        | `esc_attr__()`, `esc_attr_e()`               |
| Escape HTML Output             | `esc_html__()`, `esc_html_e()`               |
| Escape URL Output              | `esc_url()`, `esc_url_raw()`                 |
| Restrict HTML Output           | `wp_kses()`                                  |
| Localize/Translate             | `__()`, `_e()`, `esc_html__()`, etc.         |
| Print Output                   | Use escaping + i18n function (never `echo` directly) |
| Nonce Security                 | `wp_nonce_field()`, `wp_verify_nonce()`       |
| User Capability Check          | `current_user_can()`                         |
| Remote Data (HTTP)             | `wp_remote_get()`, `wp_remote_post()`        |

**Always ensure code is secure, sanitized, escaped, localized, and respects user roles and permissions. All HTTP requests must use `wp_remote_get()`/`wp_remote_post()` according to [the documentation](https://developer.wordpress.org/reference/functions/wp_remote_get/) and data must be validated.**
