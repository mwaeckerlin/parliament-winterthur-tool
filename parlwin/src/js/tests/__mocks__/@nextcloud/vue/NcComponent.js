import { defineComponent } from 'vue'

export default defineComponent({
  name: 'NcStub',
  template: '<div><slot /><slot name="default" /></div>',
})
