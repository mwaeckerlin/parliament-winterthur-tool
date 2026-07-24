<template>
  <div class="pw-wysiwyg" :class="{ 'pw-wysiwyg--readonly': !editable }">
    <div v-if="editable && editor" class="pw-wysiwyg__toolbar" role="toolbar" aria-label="Formatierung">
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('bold') }" title="Fett (Ctrl+B)" @mousedown.prevent @click.prevent="editor.chain().focus().toggleBold().run()"><PwWysiwygIcon name="bold" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('italic') }" title="Kursiv (Ctrl+I)" @mousedown.prevent @click.prevent="editor.chain().focus().toggleItalic().run()"><PwWysiwygIcon name="italic" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('underline') }" title="Unterstrichen (Ctrl+U)" @mousedown.prevent @click.prevent="editor.chain().focus().toggleUnderline().run()"><PwWysiwygIcon name="underline" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('strike') }" title="Durchgestrichen" @mousedown.prevent @click.prevent="editor.chain().focus().toggleStrike().run()"><PwWysiwygIcon name="strike" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('paragraph') }" title="Absatz" @mousedown.prevent @click.prevent="editor.chain().focus().setParagraph().run()"><PwWysiwygIcon name="paragraph" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('heading', { level: 2 }) }" title="Überschrift 2" @mousedown.prevent @click.prevent="editor.chain().focus().toggleHeading({ level: 2 }).run()"><PwWysiwygIcon name="h2" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('heading', { level: 3 }) }" title="Überschrift 3" @mousedown.prevent @click.prevent="editor.chain().focus().toggleHeading({ level: 3 }).run()"><PwWysiwygIcon name="h3" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('bulletList') }" title="Aufzählung" @mousedown.prevent @click.prevent="editor.chain().focus().toggleBulletList().run()"><PwWysiwygIcon name="bulletList" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('orderedList') }" title="Nummerierte Liste" @mousedown.prevent @click.prevent="editor.chain().focus().toggleOrderedList().run()"><PwWysiwygIcon name="orderedList" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('blockquote') }" title="Zitat" @mousedown.prevent @click.prevent="editor.chain().focus().toggleBlockquote().run()"><PwWysiwygIcon name="blockquote" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('code') }" title="Code (verbatim)" @mousedown.prevent @click.prevent="editor.chain().focus().toggleCode().run()"><PwWysiwygIcon name="code" /></button>
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('codeBlock') }" title="Code-Block (verbatim)" @mousedown.prevent @click.prevent="editor.chain().focus().toggleCodeBlock().run()"><PwWysiwygIcon name="codeBlock" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :class="{ aktiv: editor.isActive('link') }" title="Link einfügen / bearbeiten" @mousedown.prevent @click.prevent="linkSetzen"><PwWysiwygIcon name="link" /></button>
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.isActive('link')" title="Link entfernen" @mousedown.prevent @click.prevent="editor.chain().focus().unsetLink().run()"><PwWysiwygIcon name="linkOff" /></button>
      </div>
      <div class="pw-wysiwyg__gruppe">
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.can().chain().focus().undo().run()" title="Rückgängig (Ctrl+Z)" @mousedown.prevent @click.prevent="editor.chain().focus().undo().run()"><PwWysiwygIcon name="undo" /></button>
        <button type="button" class="pw-wysiwyg__btn" :disabled="!editor.can().chain().focus().redo().run()" title="Wiederholen (Ctrl+Shift+Z)" @mousedown.prevent @click.prevent="editor.chain().focus().redo().run()"><PwWysiwygIcon name="redo" /></button>
        <button type="button" class="pw-wysiwyg__btn" title="Formatierung entfernen" @mousedown.prevent @click.prevent="editor.chain().focus().unsetAllMarks().clearNodes().run()"><PwWysiwygIcon name="clear" /></button>
      </div>
      <div v-if="revisionen.length" class="pw-wysiwyg__gruppe">
        <button v-if="kannZurueck" type="button" class="pw-wysiwyg__btn" title="Eine Version zurück" @mousedown.prevent @click.prevent="versionZurueck">←</button>
        <button v-if="imVerlauf" type="button" class="pw-wysiwyg__btn" title="Eine Version vorwärts" @mousedown.prevent @click.prevent="versionVorwaerts">→</button>
        <button v-if="imVerlauf" type="button" class="pw-wysiwyg__btn" title="Zur neuesten Version" @mousedown.prevent @click.prevent="zeigeNeueste">»</button>
      </div>
      <div v-if="$slots.toolbarExtra || pdfHref" class="pw-wysiwyg__gruppe pw-wysiwyg__gruppe--rechts">
        <slot name="toolbarExtra" />
        <a v-if="pdfHref" :href="pdfHref" target="_blank" rel="noopener" class="pw-wysiwyg__btn" :title="pdfTitle || 'Als PDF herunterladen'">
          <PwWysiwygIcon name="pdf" />
        </a>
      </div>
      <span v-if="imVerlauf" class="pw-wysiwyg__status">Ältere Fassung – nur Ansicht</span>
      <span v-else-if="status" class="pw-wysiwyg__status">{{ status }}</span>
    </div>
    <editor-content :editor="editor" class="pw-wysiwyg__editor" />
  </div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { Markdown } from 'tiptap-markdown'
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
    /** Archivierte Vorversionen (älteste zuerst) — ermöglichen das Blättern im Verlauf. */
    revisionen: { type: Array, default: () => [] },
  },
  emits: ['update:modelValue', 'blur', 'versionAngezeigt'],
  data() {
    // revisionIndex === null → aktuelle (neueste) Fassung.
    // arbeitstext: der beim Beginn des Blätterns festgehaltene Bearbeitungsstand.
    // Er – nicht die zuletzt gespeicherte Fassung (modelValue) – kommt beim » zurück.
    return { editor: null, revisionIndex: null, arbeitstext: null }
  },
  computed: {
    /** Es wird gerade eine ältere Fassung angezeigt (nur Ansicht). */
    imVerlauf() {
      return this.revisionIndex !== null
    },
    kannZurueck() {
      if (!this.revisionen.length) return false
      return this.revisionIndex === null || this.revisionIndex > 0
    },
  },
  watch: {
    modelValue(neu) {
      if (!this.editor || this.imVerlauf) return
      const aktuell = this.editor.storage.markdown.getMarkdown()
      if (neu === aktuell) return
      // Tiptap v3: emitUpdate steckt im Options-Objekt (der frühere 2. Boolean-Param
      // wird ignoriert). Ohne { emitUpdate: false } feuert onUpdate und überschriebe
      // den Arbeitsstand der übergeordneten Ansicht.
      this.editor.commands.setContent(neu || '', { emitUpdate: false })
    },
    editable(neu) {
      if (this.editor && !this.imVerlauf) this.editor.setEditable(neu)
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
        // Notizen werden intern als Markdown gespeichert (nicht als HTML).
        Markdown.configure({ html: true, transformPastedText: true, transformCopiedText: true }),
      ],
      onUpdate: ({ editor }) => {
        // Beim Blättern zeigt der Editor eine ältere Fassung an; dieser Inhalt ist
        // NICHT der Arbeitsstand und darf ihn in der übergeordneten Ansicht nicht
        // überschreiben. (Zusätzliche Absicherung zu { emitUpdate: false }, das je
        // nach Tiptap-Interna nicht in jedem Fall greift.)
        if (this.imVerlauf) return
        this.$emit('update:modelValue', editor.storage.markdown.getMarkdown() || '')
      },
      onBlur: ({ event }) => this.handleBlur(event),
    })
  },
  beforeUnmount() {
    if (this.editor) {
      this.editor.destroy()
      this.editor = null
    }
  },
  methods: {
    /**
     * Behandelt den Fokus-Verlust des Editors.
     *
     * - Beim Blättern im Verlauf (imVerlauf) darf KEIN Speichern ausgelöst werden:
     *   der Wechsel in eine ältere Fassung setzt den Editor per setEditable(false)
     *   read-only, was einen programmatischen Blur auslöst (Back-Pfeil → sofortiges
     *   «Notiz gespeichert»). Das ist kein echtes Verlassen des Feldes.
     * - Ein Klick auf die eigene Toolbar (relatedTarget im eigenen Element) ist
     *   ebenfalls kein Verlassen — er darf weder speichern noch den Text verwerfen.
     */
    handleBlur(event) {
      if (this.imVerlauf) return
      const ziel = event?.relatedTarget
      if (ziel && this.$el?.contains?.(ziel)) return
      this.$emit('blur')
    },
    /**
     * Blättert eine Version zurück. Von der aktuellen Fassung aus landet man
     * bei der jüngsten archivierten Version.
     */
    versionZurueck() {
      if (this.revisionIndex === null) {
        // Beginn des Blätterns: den aktuellen Bearbeitungsstand festhalten, damit
        // » ihn (und nicht die zuletzt gespeicherte Fassung) wiederherstellt.
        this.arbeitstext = this.editor?.storage.markdown.getMarkdown() || ''
      }
      const ziel = this.revisionIndex === null
        ? this.revisionen.length - 1
        : this.revisionIndex - 1
      if (ziel < 0) return
      this.zeigeVersion(ziel)
    },
    versionVorwaerts() {
      if (this.revisionIndex === null) return
      const ziel = this.revisionIndex + 1
      if (ziel > this.revisionen.length - 1) {
        this.zeigeNeueste()
        return
      }
      this.zeigeVersion(ziel)
    },
    /** Ältere Fassungen sind nur Ansicht — bearbeitet wird immer der Arbeitsstand. */
    zeigeVersion(index) {
      if (!this.editor) return
      this.revisionIndex = index
      this.editor.setEditable(false)
      // { emitUpdate: false } (Tiptap v3): Blättern darf den Arbeitsstand der
      // übergeordneten Ansicht NICHT überschreiben – sonst würde beim Ok/Restore die
      // angezeigte alte Fassung statt des Arbeitsstands gespeichert.
      this.editor.commands.setContent(this.revisionen[index]?.text || '', { emitUpdate: false })
      // Der übergeordneten Ansicht mitteilen, welche alte Fassung angezeigt wird
      // (für den Restore beim expliziten Ok).
      this.$emit('versionAngezeigt', this.revisionen[index]?.text ?? '')
    },
    zeigeNeueste() {
      if (!this.editor) return
      this.revisionIndex = null
      // Den festgehaltenen Arbeitsstand zurückholen, NICHT die gespeicherte Fassung.
      const stand = this.arbeitstext ?? this.modelValue ?? ''
      this.editor.commands.setContent(stand, { emitUpdate: false })
      this.editor.setEditable(this.editable)
      this.$emit('versionAngezeigt', null)
    },
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
