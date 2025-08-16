// Global type definitions for WordPress and WooCommerce

declare global {
  interface Window {
    bh_wp_bitcoin_gateway_order_details: {
      btc_address: string;
      btc_total: string;
      order_id: string;
      btc_amount_received: string;
      status: string;
      amount_received: string;
      order_status_formatted: string;
      last_checked_time_formatted: string;
      [key: string]: any;
    };
    bh_wp_bitcoin_gateway_ajax_data: {
      ajax_url: string;
      nonce: string;
    };
    jQuery: JQueryStatic;
  }

  const __: (text: string, domain?: string) => string;
  const jQuery: JQueryStatic;
  const $: JQueryStatic;
}

// WooCommerce Blocks types
declare module '@woocommerce/blocks-registry' {
  export function registerPaymentMethod(config: PaymentMethodConfig): void;
}

declare module '@woocommerce/settings' {
  export function getSetting(key: string, defaultValue?: any): any;
}

declare module '@wordpress/html-entities' {
  export function decodeEntities(text: string): string;
}

interface PaymentMethodConfig {
  name: string;
  label: React.ReactElement;
  content: React.ReactElement;
  edit: React.ReactElement;
  canMakePayment: () => boolean;
  ariaLabel: string;
  supports: {
    features: string[];
  };
}

interface PaymentMethodProps {
  components: {
    PaymentMethodLabel: React.ComponentType<{ text: string }>;
  };
}

interface AjaxResponse {
  data: {
    btc_amount_received: string;
    status: string;
    amount_received: string;
    order_status_formatted: string;
    last_checked_time_formatted: string;
    [key: string]: any;
  };
}

export {};