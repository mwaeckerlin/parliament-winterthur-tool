<template>
  <div class="pw-wysiwyg" :class="{ 'pw-wysiwyg--readonly': !editable }">
    <div v-if="editable && editor" class="pw-wysiwyg__toolbar" role="toolbar" aria-label="Formatierung">
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('bold') }" title="Fett (Ctrl+B)" @click.prevent="editor.chain().focus().toggleBold().run()"><PwWysiwygIcon name="bold" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('italic') }" title="Kursiv (Ctrl+I)" @click.prevent="editor.chain().focus().toggleItalic().run()"><PwWysiwygIcon name="italic" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('underline') }" title="Unterstrichen (Ctrl+U)" @click.prevent="editor.chain().focus().toggleUnderline().run()"><PwWysiwygIcon name="underline" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('strike') }" title="Durchgestrichen" @click.prevent="editor.chain().focus().toggleStrike().run()"><PwWysiwygIcon name="strike" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('paragraph') }" title="Absatz" @click.prevent="editor.chain().focus().setParagraph().run()"><PwWysiwygIcon name="paragraph" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('heading', { level: 2 }) }" title="Überschrift 2" @click.prevent="editor.chain().focus().toggleHeading({ level: 2 }).run()"><PwWysiwygIcon name="h2" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('heading', { level: 3 }) }" title="Überschrift 3" @click.prevent="editor.chain().focus().toggleHeading({ level: 3 }).run()"><PwWysiwygIcon name="h3" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('bulletList') }" title="Aufzählung" @click.prevent="editor.chain().focus().toggleBulletList().run()"><PwWysiwygIcon name="bulletList" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('orderedList') }" title="Nummerierte Liste" @click.prevent="editor.chain().focus().toggleOrderedList().run()"><PwWysiwygIcon name="orderedList" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('blockquote') }" title="Zitat" @click.prevent="editor.chain().focus().toggleBlockquote().run()"><PwWysiwygIcon name="blockquote" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('link') }" title="Link einfügen / bearbeiten" @click.prevent="linkSetzen"><PwWysiwygIcon name="link" /></button>
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.isActive('link')" title="Link entfernen" @click.prevent="editor.chain().focus().unsetLink().run()"><PwWysiwygIcon name="linkOff" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.can().chain().focus().undo().run()" title="Rückgängig (Ctrl+Z)" @click.prevent="editor.chain().focus().undo().run()"><PwWysiwygIcon name="undo" /></button>
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.can().chain().focus().redo().run()" title="Wiederholen (Ctrl+Shift+Z)" @click.prevent="editor.chain().focus().redo().run()"><PwWysiwygIcon name="redo" /></button>
        <button type="button" class="pw-wysiwyg__btn" title="Formatierung entfernen" @click.prevent="editor.chain().focus().unsetAllMarks().clearNodes().run()"><PwWysiwygIcon name="clear" /></button>
      </div>
      <div v-if="$slots.toolbarExtra || pdfHref" class="pw-wysiwyg__gruppe pw-wysiwyg__gruppe--rechts">
        <slot name="toolbarExtra" />
        <a v-if="pdfHref" :href="pdfHref" target="_blank" rel="noopener" class="pw-wysiwyg__btn" :title="pdfTitle || 'Als PDF herunterladen'">
          <PwWysiwygIcon name="pdf" />
        </a>
      </div>
      <span v-if="status" class="pw-wysiwyg__status">{{ status }}</span>
    </div>
    <div class="pw-wysiwyg__body">
      <editor-content :editor="editor" class="pw-wysiwyg__editor" />
    </div>
  </div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import PwWysiwygIcon from './PwWysiwygIcons.vue'

export default {
  name: 'PwWysiwyg',
  components: { EditorContent, PwWysiwygIcon },
  props: {
    modelValue: { type: String, default: '' },
    editable: { type: Boolean, default: true },
    placeholder: { type: String, default: '' },
    status: { type: String, default: '' },
    pdfHref: { type: String, default: '' },
    pdfTitle: { type: String, default: '' },
  },
  emits: ['update:modelValue', 'blur'],
  data() {
    return { editor: null }
  },
  watch: {
    modelValue(neu) {
      if (!this.editor) return
      const aktuell = this.editor.getHTML()
      if (neu === aktuell) return
      this.editor.commands.setContent(neu || '', false)
    },
    editable(neu) {
      if (this.editor) this.editor.setEditable(neu)
    },
  },
  mounted() {
    this.editor = new Editor({
      content: this.modelValue || '',
      editable: this.editable,
      extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false, autolink: true }),
        Placeholder.configure({ placeholder: this.placeholder }),
      ],
      onUpdate: ({ editor }) => {
        const html = editor.getHTML()
        const normalisiert = html === '<p></p>' ? '' : html
        this.$emit('update:modelValue', normalisiert)
      },
      onBlur: () => {
        this.$emit('blur')
      },
    })
  },
  beforeUnmount() {
    if (this.editor) {
      this.editor.destroy()
      this.editor = null
    }
  },
  methods: {
    linkSetzen() {
      const aktuell = this.editor.getAttributes('link').href || ''
      // eslint-disable-next-line no-alert
      const url = window.prompt('Link-URL (leer = entfernen)', aktuell)
      if (url === null) return
      if (url === '') {
        this.editor.chain().focus().extendMarkRange('link').unsetLink().run()
        return
      }
      this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    },
  },
}
</script>
