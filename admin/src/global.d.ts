interface Window {
  datatable: { [key: string]: any };
  normalCaptcha: boolean
  reloadTable: Function
  callbackSubmit: Function | null
  initResendModal: Function | null
  submit: Function | null
  syncEditorsBeforeSubmit: (() => void) | null
  CKEDITOR_BASEPATH?: string
  CKEDITOR?: {
    config: Record<string, unknown>;
    instances: Record<string, { updateElement: () => void; setData: (data: string, options?: { callback?: () => void }) => void; element?: { $?: Element } }>;
    replace: (element: string | HTMLTextAreaElement, config?: Record<string, unknown>) => unknown;
  }
}
declare var isDevelopment: boolean;
declare module '*.scss';
