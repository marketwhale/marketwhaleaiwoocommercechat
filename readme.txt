=== MarketWhaleAI ===
Contributors: marketwhaleai
Link: https://aiforbusinesses.marketwhaleai.com/
Tags: woocommerce, ai, chat, product suggestions, seo, bulk categories, gemini, google ai
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered floating chat widget with WooCommerce product suggestions (Gemini API + SerpAPI), dynamic shop enhancements, and admin tools for SEO and bulk category management.

== Description ==
MarketWhaleAI is a comprehensive WordPress plugin engineered to integrate intelligent, AI-powered capabilities into your WooCommerce store. It aims to elevate the customer shopping experience and streamline administrative tasks, making your online store more dynamic and efficient.

**Key Features:**

*   **AI Chat Widget (Frontend):** A floating conversational assistant offering instant support and personalized shopping guidance.
    *   Interactive Shopping Assistant: Provides a direct line of communication for customers to interact with an AI.
    *   Product Discovery: Understands product-related queries and suggests relevant items from your store.
    *   Detailed Information & Comparison: Customers can select multiple items within the chat to get more detailed information or a side-by-side comparison.

*   **Shop Page Enhancements (Frontend):** Transforms standard WooCommerce shop, category, and tag archive pages into a modern, dynamic browsing experience. This functionality can also be embedded on any page using a shortcode.
    *   Dynamic Browsing Experience: Replaces default product listings with an interactive and visually appealing layout.
    *   Category Scrollers: Presents product categories and subcategories in intuitive, horizontally scrollable lists.
    *   Infinite Product Grid: Products are displayed in a responsive grid that loads more items automatically as the customer scrolls down.
    *   Embeddable Shop Browser (`[mwai_shop_browser]` shortcode): Place the entire dynamic shop browsing experience on any WordPress page.

*   **Admin Tools (For Store Administrators):** Powerful tools within your WordPress admin dashboard to manage AI settings, optimize product SEO, and streamline category management.
    *   Gemini API Settings: Configure the core AI functionality, including API key and model parameters.
    *   Product SEO Generation: Leverages Gemini AI to automatically generate SEO-optimized content for your WooCommerce products.
    *   Bulk Category Management: Simplifies creating and organizing multiple product categories and subcategories at once.
    *   Custom Shortcodes: Provides flexible shortcodes (`[mwai_products]`, `[mwai_category_scroller]`, `[mwai_product_scroller]`) to display various product and category layouts anywhere on your site.

== Installation ==

1.  **Upload:** Upload the `marketwhaleai` folder to the `/wp-content/plugins/` directory via FTP or use the WordPress plugin uploader.
2.  **Activate:** Activate the plugin through the 'Plugins' menu in WordPress.
3.  **Configure API Key:** Navigate to `MarketWhaleAI` in your WordPress admin menu and enter your Gemini API Key.
4.  **Start Using:** The AI chat widget will appear on your frontend, and admin tools will be available in product edit screens and category management.

== Frequently Asked Questions ==

= What is the Gemini API Key for? =
The Gemini API Key is required to enable the AI functionalities of the chat widget and the product SEO generation. You can obtain one from [Google AI Studio](https://ai.google.dev/).

= Does this plugin work with all WordPress themes? =
MarketWhaleAI is designed to be compatible with most standard WordPress themes, especially those that follow WooCommerce templating best practices. However, due to the dynamic nature of the shop enhancements, some theme-specific CSS might need minor adjustments.

= How do I use the custom shortcodes? =
You can use shortcodes like `[mwai_products]`, `[mwai_shop_browser]`, `[mwai_category_scroller]`, and `[mwai_product_scroller]` in any post, page, or widget area. Refer to the plugin's documentation (or the plugin settings page after installation) for detailed attribute usage.

= Is the chat widget customizable? =
The chat widget's appearance is primarily controlled by the `style.css` file within the plugin's assets. Advanced customization would require modifying this CSS.


== Changelog ==

= 1.6 =
*   Initial release with AI Chat Widget, Shop Enhancements, Product SEO Generation, and Bulk Category Management.
