<template>
  <!-- Filter im Navigations-Slot — wie alle anderen Ansichten (kein eigenes
       Filterband auf der Seite). -->
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcSelect v-if="jahrOptionen.length" v-model="jahrOption" :options="jahrOptionen" :clearable="false" input-label="Budgetjahr" @update:model-value="jahrGewechselt" />
      <NcSelect v-if="kommissionOptionen.length" v-model="kommissionOption" :options="kommissionOptionen" input-label="Zuständige Kommission" placeholder="Alle" @update:model-value="kommissionGewechselt" />
      <NcSelect v-model="departementOption" :options="departementOptionen" input-label="Departement" placeholder="Alle" @update:model-value="ladeAnsicht(false)" />
      <NcTextField v-model="minProzent" type="number" label="Anstieg ab %" />
      <NcTextField v-model="minAbsolut" type="number" label="Anstieg ab CHF" />
      <NcSelect v-model="antragsartOption" :options="antragsartOptionen" :clearable="false" input-label="Anträge" />
      <NcCheckboxRadioSwitch :model-value="sitzungsmodus" type="switch" @update:model-value="sitzungsmodusUmschalten">
        Sitzungsmodus (offizielle Sitzungsanträge)
      </NcCheckboxRadioSwitch>
    </div>
  </Teleport>

  <section class="pw-view-content pw-budget">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Budget</h2>
      <span class="pw-view-count">{{ produktegruppenCount }}</span>
      <NcButton type="secondary" @click="pdfOeffnen">Anträge als PDF</NcButton>
      <NcButton v-if="novemberbriefMoeglich" type="secondary" @click="novemberbriefEinlesen">Novemberbrief einlesen</NcButton>
      <NcButton type="primary" @click="neuOeffnen">+ Neu</NcButton>
    </header>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>
    <NcEmptyContent v-else-if="!ansicht" name="Kein Budgetjahr vorhanden" description="Über «+ Neu» ein vergangenes Budgetjahr importieren." />

    <template v-else>
      <!-- Summenzeile (budget-eigen, richtet sich nach den Filtern) -->
      <div class="pw-budget-summen">
        <div class="pw-summe">
          <span class="pw-summe-label">Stellen</span>
          <span class="pw-summe-wert">{{ zahl(summen.stellen) }}</span>
          <span class="pw-summe-diff" :class="diffKlasse(summen.stellenDiff)">{{ diff(summen.stellenDiff, true) }}</span>
        </div>
        <div class="pw-summe">
          <span class="pw-summe-label">Ausgaben</span>
          <span class="pw-summe-wert">{{ fr(summen.ausgaben) }}</span>
          <span class="pw-summe-diff" :class="diffKlasse(summen.ausgabenDiff)">{{ diff(summen.ausgabenDiff) }}</span>
        </div>
        <div class="pw-summe">
          <span class="pw-summe-label">Einnahmen</span>
          <span class="pw-summe-wert">{{ fr(summen.einnahmen) }}</span>
          <span class="pw-summe-diff" :class="diffKlasse(summen.einnahmenDiff)">{{ diff(summen.einnahmenDiff) }}</span>
        </div>
        <div class="pw-summe">
          <span class="pw-summe-label">{{ summen.ergebnis < 0 ? 'Defizit' : 'Ertrag' }}</span>
          <span class="pw-summe-wert" :class="summen.ergebnis < 0 ? 'pw-negativ' : 'pw-positiv'">{{ fr(summen.ergebnis) }}</span>
          <span class="pw-summe-diff" :class="diffKlasse(summen.ergebnisDiff)">{{ diff(summen.ergebnisDiff) }}</span>
        </div>
        <div class="pw-summe">
          <span class="pw-summe-label">Steuerfuss</span>
          <span class="pw-summe-wert">{{ steuerfussEffektiv }}%</span>
        </div>
      </div>

      <div class="pw-budget-tabs" role="tablist">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          role="tab"
          class="pw-budget-tab"
          :class="{ 'pw-budget-tab-aktiv': aktiverTab === t.key }"
          :aria-selected="aktiverTab === t.key"
          @click="aktiverTab = t.key"
        >{{ t.label }}</button>
      </div>

      <!-- Tab: Globalbudgets -->
      <div v-show="aktiverTab === 'globalbudget'" class="pw-budget-tabpanel">
        <div v-if="!sitzungsmodus" class="pw-data-card pw-verteilung">
          <NcCheckboxRadioSwitch :model-value="verteilung.automatikEin" type="switch" @update:model-value="verteilungAendern('automatikEin', $event)">
            Defizit automatisch als Pauschalkürzung verteilen
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-if="verteilung.automatikEin" :model-value="pauschalEinreichen" type="switch" @update:model-value="pauschalEinreichenUmschalten">
            Pauschalantrag einreichen
          </NcCheckboxRadioSwitch>
          <div class="pw-verteilung-ziel">
            <NcSelect :model-value="zielModusOption" :options="zielModusOptionen" :clearable="false" input-label="Ziel" @update:model-value="verteilungAendern('zielModus', $event ? $event.value : 'schwarze_null')" />
            <NcTextField v-if="verteilung.zielModus !== 'schwarze_null'" v-model="zielBetrag" type="number" label="Zielbetrag CHF" />
            <NcButton v-if="!verteilung.automatikEin" type="secondary" @click="pauschalVerteilen">Defizit verteilen</NcButton>
          </div>
        </div>

        <!-- Weitere, voneinander unabhängige Pauschalanträge (F100) -->
        <div v-if="!sitzungsmodus" class="pw-data-card pw-pauschalantraege">
          <h3 class="pw-pauschal-titel">Weitere Pauschalanträge</h3>
          <BudgetPauschalForm
            v-for="p in pauschalantraege"
            :key="p.id"
            :pauschal="p"
            :produktegruppen="ansicht.produktegruppen"
            @save="felder => pauschalAendern(p.id, felder)"
            @delete="pauschalLoeschen(p.id)"
          />
          <NcButton type="secondary" @click="pauschalErstellen">+ Pauschalantrag</NcButton>
        </div>

        <div v-for="dep in gruppenNachDepartement" :key="dep.name" class="pw-budget-departement">
          <h3 class="pw-budget-dep-titel">{{ dep.name }}</h3>
          <div class="pw-card-grid">
            <article v-for="g in dep.gruppen" :key="g.id" class="pw-data-card">
              <div class="pw-data-card-header">
                <div>
                  <p class="pw-data-card-kicker">Produktegruppe {{ g.code }}</p>
                  <h3>{{ g.name }}</h3>
                </div>
                <span class="pw-budget-betrag">
                  {{ fr(g.globalkredit.soll) }}
                  <span class="pw-summe-diff" :class="diffKlasse(g.globalkredit.soll - g.globalkredit.sollVorjahr)">{{ diff(g.globalkredit.soll - g.globalkredit.sollVorjahr) }}</span>
                </span>
              </div>
              <ul v-if="antraegeFuer(g.code).length" class="pw-budget-antraege">
                <li v-for="a in antraegeFuer(g.code)" :key="a.id" :class="{ 'pw-antrag-auto': a.automatisch }">
                  <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                  <span class="pw-antrag-steller">{{ a.antragsteller || (a.automatisch ? 'automatisch' : '') }}</span>
                  <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                  <NcSelect v-if="!a.automatisch" :model-value="haltungOptFor(a)" :options="haltungOptionen(a.herkunft)" :clearable="false" aria-label="Unsere Haltung" class="pw-antrag-haltung" @update:model-value="antragHaltung(a.id, $event ? $event.value : a.haltung)" />
                  <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                  <span class="pw-entscheid-knoepfe">
                    <NcButton type="tertiary" :aria-label="'Antrag angenommen'" @click="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                    <NcButton type="tertiary" :aria-label="'Antrag abgelehnt'" @click="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                  </span>
                  <NcButton v-if="!a.automatisch" type="tertiary" aria-label="Antrag löschen" @click="antragLoeschen(a.id)">🗑</NcButton>
                  <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                  <details class="pw-antrag-notizen" @toggle="notizenToggle(a.id, $event)">
                    <summary>Notizen</summary>
                    <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                  </details>
                </li>
              </ul>
              <NcCheckboxRadioSwitch
                v-if="!sitzungsmodus && verteilung.automatikEin"
                :model-value="pauschalAusnahmen.includes(g.code)"
                type="checkbox"
                @update:model-value="ausnahmeUmschalten(g.code, $event)"
              >Ausnahme vom Pauschalantrag</NcCheckboxRadioSwitch>
              <BudgetAntragForm
                v-if="formOffen['g' + g.code]"
                :form="neu[g.code]"
                :antragsteller-optionen="antragstellerOptionen(neu[g.code].herkunft)"
                :haltung-optionen="haltungOptionen(neu[g.code].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                :basis="g.globalkredit.soll"
                @herkunft="herkunftGewechselt(neu[g.code], $event)"
                @save="antragGlobalbudget(g.code)"
              />
              <NcButton v-else type="tertiary" @click="formOeffnen('g' + g.code)">+ Antrag</NcButton>
              <details v-if="g.auftrag" class="pw-budget-info">
                <summary>Auftrag</summary>
                <p>{{ g.auftrag }}</p>
              </details>
              <details v-if="g.produkte && g.produkte.length" class="pw-budget-info">
                <summary>Produkte (Information)</summary>
                <ul>
                  <li v-for="(p, i) in g.produkte" :key="i">
                    {{ p.nummer }} {{ p.name }}
                    <span v-if="p.nettokosten && p.nettokosten.soll" class="pw-produkt-kosten">{{ fr(p.nettokosten.soll) }}</span>
                  </li>
                </ul>
              </details>
              <details v-if="g.begruendungAbweichung || g.erlaeuterungStellen || g.begruendungFap || g.massnahmen" class="pw-budget-info">
                <summary>Erläuterungen</summary>
                <template v-if="g.begruendungAbweichung">
                  <h4>Begründung Abweichung</h4>
                  <p>{{ g.begruendungAbweichung }}</p>
                </template>
                <template v-if="g.erlaeuterungStellen">
                  <h4>Erläuterungen zum Stellenplan</h4>
                  <p>{{ g.erlaeuterungStellen }}</p>
                </template>
                <template v-if="g.begruendungFap">
                  <h4>Begründung FAP</h4>
                  <p>{{ g.begruendungFap }}</p>
                </template>
                <template v-if="g.massnahmen">
                  <h4>Wesentliche Massnahmen und Projekte</h4>
                  <p>{{ g.massnahmen }}</p>
                </template>
              </details>
            </article>
          </div>
        </div>
      </div>

      <!-- Tab: Personalbestand -->
      <div v-show="aktiverTab === 'personal'" class="pw-budget-tabpanel">
        <div v-for="dep in gruppenNachDepartement" :key="dep.name" class="pw-budget-departement">
          <h3 class="pw-budget-dep-titel">{{ dep.name }}</h3>
          <div class="pw-card-grid">
            <article v-for="g in dep.gruppen" :key="g.id" class="pw-data-card">
              <div class="pw-data-card-header">
                <div>
                  <p class="pw-data-card-kicker">Produktegruppe {{ g.code }}</p>
                  <h3>{{ g.name }}</h3>
                </div>
                <span class="pw-budget-betrag">
                  {{ zahl(g.stellen.soll) }} Stellen
                  <span class="pw-summe-diff" :class="diffKlasse(g.stellen.soll - g.stellen.sollVorjahr)">{{ diff(g.stellen.soll - g.stellen.sollVorjahr, true) }}</span>
                </span>
              </div>
              <ul v-if="antraegeBereich('personal', g.code).length" class="pw-budget-antraege">
                <li v-for="a in antraegeBereich('personal', g.code)" :key="a.id">
                  <span class="pw-antrag-betrag" :class="diffKlasse(a.stellenDelta)">{{ zahl(a.stellenDelta) }} Stellen</span>
                  <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                  <span class="pw-antrag-steller">{{ a.antragsteller }}</span>
                  <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                  <NcSelect v-if="!a.automatisch" :model-value="haltungOptFor(a)" :options="haltungOptionen(a.herkunft)" :clearable="false" aria-label="Unsere Haltung" class="pw-antrag-haltung" @update:model-value="antragHaltung(a.id, $event ? $event.value : a.haltung)" />
                  <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                  <span class="pw-entscheid-knoepfe">
                    <NcButton type="tertiary" aria-label="Antrag angenommen" @click="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                    <NcButton type="tertiary" aria-label="Antrag abgelehnt" @click="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                  </span>
                  <NcButton type="tertiary" aria-label="Antrag löschen" @click="antragLoeschen(a.id)">🗑</NcButton>
                  <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                  <details class="pw-antrag-notizen" @toggle="notizenToggle(a.id, $event)">
                    <summary>Notizen</summary>
                    <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                  </details>
                </li>
              </ul>
              <BudgetAntragForm
                v-if="formOffen['p' + g.code]"
                :form="neuPersonal[g.code]"
                :antragsteller-optionen="antragstellerOptionen(neuPersonal[g.code].herkunft)"
                :haltung-optionen="haltungOptionen(neuPersonal[g.code].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                mit-stellen
                @herkunft="herkunftGewechselt(neuPersonal[g.code], $event)"
                @save="antragPersonal(g.code)"
              />
              <NcButton v-else type="tertiary" @click="formOeffnen('p' + g.code)">+ Antrag</NcButton>
            </article>
          </div>
        </div>
      </div>

      <!-- Tab: Investitionsrechnung -->
      <div v-show="aktiverTab === 'investition'" class="pw-budget-tabpanel">
        <div v-for="dep in investitionenNachDepartement" :key="dep.name" class="pw-budget-departement">
          <h3 class="pw-budget-dep-titel">{{ dep.name }}</h3>
          <div class="pw-card-grid">
            <article v-for="i in dep.projekte" :key="i.id" class="pw-data-card">
              <div class="pw-data-card-header">
                <div>
                  <p class="pw-data-card-kicker">{{ i.cluster || 'Projekt' }}</p>
                  <h3>{{ i.projekt }}</h3>
                </div>
                <span class="pw-budget-betrag">{{ fr(i.bu) }}</span>
              </div>
              <div class="pw-data-card-grid">
                <div class="pw-data-pair"><span>Gesamtkosten</span><strong>{{ fr(i.gesamtkosten) }}</strong></div>
                <div class="pw-data-pair"><span>bereits getätigt</span><strong>{{ fr(i.bereitsGetaetigt) }}</strong></div>
                <div class="pw-data-pair"><span>künftig</span><strong>{{ fr(i.fap1 + i.fap2 + i.fap3) }}</strong></div>
                <div v-if="i.planungskosten" class="pw-data-pair"><span>Planung</span><strong>{{ fr(i.planungskosten) }}</strong></div>
              </div>
              <ul v-if="antraegeBereich('investition', i.id).length" class="pw-budget-antraege">
                <li v-for="a in antraegeBereich('investition', i.id)" :key="a.id">
                  <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                  <span class="pw-antrag-steller">{{ a.antragsteller }}</span>
                  <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                  <NcSelect v-if="!a.automatisch" :model-value="haltungOptFor(a)" :options="haltungOptionen(a.herkunft)" :clearable="false" aria-label="Unsere Haltung" class="pw-antrag-haltung" @update:model-value="antragHaltung(a.id, $event ? $event.value : a.haltung)" />
                  <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                  <span class="pw-entscheid-knoepfe">
                    <NcButton type="tertiary" aria-label="Antrag angenommen" @click="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                    <NcButton type="tertiary" aria-label="Antrag abgelehnt" @click="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                  </span>
                  <NcButton type="tertiary" aria-label="Antrag löschen" @click="antragLoeschen(a.id)">🗑</NcButton>
                  <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                  <details class="pw-antrag-notizen" @toggle="notizenToggle(a.id, $event)">
                    <summary>Notizen</summary>
                    <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                  </details>
                </li>
              </ul>
              <BudgetAntragForm
                v-if="formOffen['i' + i.id]"
                :form="neuInv[i.id]"
                :antragsteller-optionen="antragstellerOptionen(neuInv[i.id].herkunft)"
                :haltung-optionen="haltungOptionen(neuInv[i.id].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                :basis="i.bu"
                @herkunft="herkunftGewechselt(neuInv[i.id], $event)"
                @save="antragInvestition(i)"
              />
              <NcButton v-else type="tertiary" @click="formOeffnen('i' + i.id)">+ Antrag</NcButton>
            </article>
          </div>
        </div>
      </div>

      <!-- Tab: Steuerfuss -->
      <div v-show="aktiverTab === 'steuerfuss'" class="pw-budget-tabpanel">
        <div class="pw-data-card pw-steuerfuss">
          <p>Geltender Steuerfuss: <strong>{{ ansicht.jahr.steuerfuss }}%</strong></p>
          <p>1 Steuerprozent ≈ {{ fr(wertProProzent) }}</p>
          <NcCheckboxRadioSwitch :model-value="steuerfussAutomatik" type="switch" @update:model-value="steuerfussAutomatikUmschalten">
            Steuerfuss bei Überschuss automatisch senken
          </NcCheckboxRadioSwitch>
          <div v-if="!steuerfussAutomatik" class="pw-verteilung-ziel">
            <NcTextField v-model="steuerfussManuell" type="number" label="Steuerfuss %" />
            <NcButton type="secondary" @click="steuerfussAntragStellen">Steuerfuss-Antrag stellen</NcButton>
          </div>
          <p v-else>Automatisch gesenkt auf <strong>{{ steuerfussEffektiv }}%</strong> ({{ ansicht.jahr.steuerfuss - steuerfussEffektiv }} Prozentpunkte).</p>
        </div>
      </div>
    </template>

    <!-- Neu: vergangenes Budgetjahr importieren — geteiltes Modal-Muster (wie Vorstoss/Geschäft). -->
    <Teleport to="body">
      <div v-if="neuOffen" class="pw-modal-overlay" @click.self="neuOffen = false">
        <div class="pw-modal">
          <div class="pw-modal-kopf">
            <h3>Vergangenes Budgetjahr importieren</h3>
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="neuOffen = false">✕</button>
          </div>
          <div class="pw-modal-body">
            <NcSelect
              v-if="importierbareOptionen.length"
              v-model="neuJahrOption"
              :options="importierbareOptionen"
              :clearable="false"
              input-label="Jahr (mit vorhandenen Budgetunterlagen)"
            />
            <p v-else class="pw-budget-info">Keine weiteren Jahre mit Budgetunterlagen verfügbar.</p>
            <NcCheckboxRadioSwitch v-model="neuMitNovemberbrief" type="switch">Mit Novemberbrief</NcCheckboxRadioSwitch>
          </div>
          <div class="pw-modal-aktionen">
            <NcButton type="primary" :disabled="!neuJahrOption" @click="jahrImportieren">Importieren</NcButton>
            <NcButton type="tertiary" @click="neuOffen = false">Abbrechen</NcButton>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PwMultiSelect from './PwMultiSelect.vue'
import BudgetAntragForm from './BudgetAntragForm.vue'
import BudgetPauschalForm from './BudgetPauschalForm.vue'
import NotizenListe from './NotizenListe.vue'
import { subscribeRealtime } from '../realtime'

const ZIEL_MODI = [
  { value: 'schwarze_null', label: 'Schwarze Null' },
  { value: 'defizit', label: 'Akzeptiertes Defizit' },
  { value: 'ertrag', label: 'Gewünschter Ertrag' },
]

// Haltung je Herkunft (F97): eigene Anträge reichen wir ein, fremde unterstützen wir.
const HALTUNG_EIGEN = [
  { value: 'einreichen', label: 'Reichen wir ein' },
  { value: 'nicht_einreichen', label: 'Reichen wir nicht ein' },
]
const HALTUNG_FREMD = [
  { value: 'unterstuetzen', label: 'Unterstützen wir' },
  { value: 'nicht_unterstuetzen', label: 'Unterstützen wir nicht' },
  { value: 'offen', label: 'Offen' },
]

export default {
  name: 'Budgetliste',
  components: { NcButton, NcSelect, NcTextField, NcCheckboxRadioSwitch, NcLoadingIcon, NcEmptyContent, PwMultiSelect, BudgetAntragForm, BudgetPauschalForm, NotizenListe },
  props: {
    kommissionen: { type: Array, default: () => [] },
    mitglieder: { type: Array, default: () => [] },
    fraktionen: { type: Array, default: () => [] },
  },
  data() {
    return {
      filterReady: false,
      // Lazy gerenderte Antragsformulare und Notizen je Position/Antrag —
      // erst bei Bedarf gemountet, damit die Seite bei vielen Produktegruppen
      // leicht bleibt (sonst Dutzende NcSelects/NotizenListen auf einmal).
      formOffen: {},
      offeneNotizen: {},
      jahre: [],
      jahrOption: null,
      departementOption: null,
      kommissionOption: null,
      minProzent: '',
      minAbsolut: '',
      sitzungsmodus: false,
      antragsartOption: { value: 'alle', label: 'Alle Anträge' },
      antragsartOptionen: [
        { value: 'alle', label: 'Alle Anträge' },
        { value: 'manuell', label: 'Nur manuelle' },
        { value: 'automatisch', label: 'Nur automatische' },
      ],
      ansicht: null,
      laden: false,
      aktiverTab: 'globalbudget',
      tabs: [
        { key: 'globalbudget', label: 'Globalbudgets' },
        { key: 'personal', label: 'Personalbestand' },
        { key: 'investition', label: 'Investitionsrechnung' },
        { key: 'steuerfuss', label: 'Steuerfuss' },
      ],
      zielModusOptionen: ZIEL_MODI,
      zielBetrag: '0',
      neu: {},
      neuPersonal: {},
      neuInv: {},
      steuerfussManuell: '',
      steuerfussManuellModus: false,
      neuOffen: false,
      neuJahrOption: null,
      importierbareJahre: [],
      neuMitNovemberbrief: false,
      unsubRealtime: null,
      ladeTimer: null,
      zielTimer: null,
    }
  },
  computed: {
    jahrGewaehlt() { return this.jahrOption },
    departementGewaehlt() { return this.departementOption },
    jahrOptionen() {
      return this.jahre.map(j => ({ value: j.jahr, label: String(j.jahr) }))
    },
    aktivePhase() {
      return this.sitzungsmodus ? 'sitzung' : 'fraktion'
    },
    eigenerName() {
      const u = getCurrentUser()
      return u ? (u.displayName || u.uid || '') : ''
    },
    aktuelleUid() {
      const u = getCurrentUser()
      return u ? u.uid : ''
    },
    // Fraktionen für die Mehrfachauswahl «unterstützende Fraktionen» (F98).
    fraktionOptionen() {
      return (this.fraktionen || [])
        .filter(f => f && f.aktiv !== false && f.name)
        .map(f => ({ value: f.name, label: f.name }))
    },
    eigeneFraktionName() {
      const f = (this.fraktionen || []).find(x => x && x.eigene)
      return f ? f.name : ''
    },
    kommissionZuordnung() {
      return this.ansicht && Array.isArray(this.ansicht.kommissionZuordnung) ? this.ansicht.kommissionZuordnung : []
    },
    kommissionOptionen() {
      const namen = []
      for (const z of this.kommissionZuordnung) {
        if (z && z.kommission && !namen.includes(z.kommission)) { namen.push(z.kommission) }
      }
      return namen.map(k => ({ value: k, label: k }))
    },
    departementOptionen() {
      let depts = this.ansicht ? this.ansicht.departemente : []
      // Verknüpfung (F76): eine gewählte Kommission engt die Departemente ein.
      if (this.kommissionOption) {
        const erlaubt = this.kommissionZuordnung
          .filter(z => z.kommission === this.kommissionOption.value)
          .map(z => z.departement)
        depts = depts.filter(d => erlaubt.includes(d))
      }
      return depts.map(d => ({ value: d, label: d }))
    },
    produktegruppenCount() {
      return this.ansicht ? this.ansicht.produktegruppen.length : 0
    },
    importierbareOptionen() {
      return this.importierbareJahre.map(j => ({ value: j, label: String(j) }))
    },
    summen() { return this.ansicht ? this.ansicht.summen : {} },
    verteilung() {
      return this.ansicht ? this.ansicht.verteilung : { automatikEin: true, zielModus: 'schwarze_null', zielBetrag: 0, haltung: 'einreichen', ausnahmen: [] }
    },
    // F100: wird der Pauschalantrag eingereicht?
    pauschalEinreichen() {
      return (this.verteilung.haltung || 'einreichen') !== 'nicht_einreichen'
    },
    // F101: Positionen, die vom Pauschalantrag ausgenommen sind.
    pauschalAusnahmen() {
      return Array.isArray(this.verteilung.ausnahmen) ? this.verteilung.ausnahmen : []
    },
    // F100: weitere (feste) Pauschalanträge neben dem automatischen Ausgleich.
    pauschalantraege() {
      return this.ansicht && Array.isArray(this.ansicht.pauschalantraege) ? this.ansicht.pauschalantraege : []
    },
    zielModusOption() {
      return ZIEL_MODI.find(m => m.value === this.verteilung.zielModus) || ZIEL_MODI[0]
    },
    standardBetragProStelle() {
      return this.ansicht ? this.ansicht.standardBetragProStelle : 200000
    },
    gruppenNachDepartement() {
      return this.gruppieren(this.ansicht ? this.ansicht.produktegruppen : [], g => g.departement, 'gruppen')
    },
    investitionenNachDepartement() {
      return this.gruppieren(this.ansicht ? this.ansicht.investitionen : [], i => i.departement, 'projekte')
    },
    wertProProzent() {
      const j = this.ansicht ? this.ansicht.jahr : null
      return j && j.steuerfuss > 0 ? Math.floor(j.steuerertrag / j.steuerfuss) : 0
    },
    steuerfussAntrag() {
      return (this.ansicht ? this.ansicht.antraege : []).find(a => a.bereich === 'steuerfuss') || null
    },
    steuerfussAutomatik() { return !this.steuerfussManuellModus && this.steuerfussAntrag === null },
    steuerfussEffektiv() {
      const j = this.ansicht ? this.ansicht.jahr : null
      if (!j) { return 0 }
      if (this.steuerfussAntrag) {
        // F96: die Prozentpunkte des Antrags ergeben den effektiven Steuerfuss.
        return this.steuerfussManuell !== '' ? Number(this.steuerfussManuell) : j.steuerfuss + (this.steuerfussAntrag.prozentDelta || 0)
      }
      const ueberschuss = this.summen.ergebnis || 0
      if (ueberschuss > 0 && this.wertProProzent > 0) {
        return j.steuerfuss - Math.floor(ueberschuss / this.wertProProzent)
      }
      return j.steuerfuss
    },
    novemberbriefMoeglich() {
      return !!(this.ansicht && this.ansicht.jahr && !this.ansicht.jahr.novemberbriefImportiert)
    },
  },
  watch: {
    minProzent() { this.ladeVerzoegert() },
    minAbsolut() { this.ladeVerzoegert() },
    zielBetrag(neu) {
      if (!this.ansicht || Number(neu) === Number(this.verteilung.zielBetrag)) { return }
      if (this.zielTimer) { clearTimeout(this.zielTimer) }
      this.zielTimer = setTimeout(() => this.verteilungZielBetrag(), 400)
    },
  },
  mounted() {
    this.ladeJahre()
    this.unsubRealtime = subscribeRealtime(this.handleRealtime)
    this.$nextTick(() => { this.filterReady = true })
  },
  beforeUnmount() {
    if (this.unsubRealtime) { this.unsubRealtime() }
  },
  methods: {
    fr(n) {
      const v = Math.round(Number(n) || 0)
      const ziffern = Math.abs(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '’')
      return (v < 0 ? '−' : '') + ziffern
    },
    zahl(n) {
      return (Number(n) || 0).toLocaleString('de-CH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
    },
    diff(n, stellen = false) {
      const v = Number(n) || 0
      if (v === 0) { return '' }
      const s = v > 0 ? '+' : '−'
      return s + (stellen ? Math.abs(v).toLocaleString('de-CH') : this.fr(Math.abs(v)))
    },
    diffKlasse(n) {
      const v = Number(n) || 0
      return v > 0 ? 'pw-diff-plus' : (v < 0 ? 'pw-diff-minus' : '')
    },
    entscheidLabel(e) {
      return { angenommen: 'angenommen', abgelehnt: 'abgelehnt' }[e] || 'offen'
    },
    gruppieren(liste, keyFn, feld) {
      const map = new Map()
      for (const el of liste) {
        const k = keyFn(el) || 'Ohne Zuordnung'
        if (!map.has(k)) { map.set(k, []) }
        map.get(k).push(el)
      }
      return [...map.entries()].map(([name, arr]) => ({ name, [feld]: arr }))
    },
    antraegeFuer(code) {
      const art = this.antragsartOption ? this.antragsartOption.value : 'alle'
      return (this.ansicht ? this.ansicht.antraege : []).filter(a =>
        a.bereich === 'globalbudget' && a.zielRef === code
        && (a.phase || 'fraktion') === this.aktivePhase
        && (art === 'alle' || (art === 'automatisch') === !!a.automatisch))
    },
    // Anträge eines Bereichs (personal/investition) je Ziel, in der aktiven Phase.
    antraegeBereich(bereich, ref) {
      return (this.ansicht ? this.ansicht.antraege : []).filter(a =>
        a.bereich === bereich && a.zielRef === String(ref)
        && (a.phase || 'fraktion') === this.aktivePhase)
    },
    async ladeJahre() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/budget/jahre'))
        this.jahre = Array.isArray(data) ? data : []
        if (this.jahre.length) {
          this.jahrOption = { value: this.jahre[0].jahr, label: String(this.jahre[0].jahr) }
          await this.ladeAnsicht()
        }
      } catch (f) {
        console.error('Budgetjahre laden fehlgeschlagen', f)
      }
    },
    async ladeAnsicht(zeigeLaden = true) {
      if (!this.jahrOption) { return }
      if (zeigeLaden) { this.laden = true }
      try {
        const params = {}
        if (this.departementOption) { params.departement = this.departementOption.value }
        if (this.kommissionOption) { params.kommission = this.kommissionOption.value }
        params.phase = this.aktivePhase
        if (this.minProzent !== '') { params.minProzent = this.minProzent }
        if (this.minAbsolut !== '') { params.minAbsolut = this.minAbsolut }
        const { data } = await axios.get(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value), { params })
        this.ansicht = data
        this.zielBetrag = String(data.verteilung.zielBetrag || 0)
        this.initEingaben()
      } catch (f) {
        console.error('Budgetansicht laden fehlgeschlagen', f)
        this.ansicht = null
      } finally {
        if (zeigeLaden) { this.laden = false }
      }
    },
    // Leeres Antragsformular (F94/F95/F97/F98): Herkunft «eigen», Antragsteller
    // vorbelegt mit dem aktuellen Nutzer, Richtung «Reduktion».
    leererAntrag(zusatz = {}) {
      return {
        betrag: '', prozent: '', mehrausgabe: false,
        herkunft: 'eigene', antragsteller: this.eigenerName,
        haltung: 'einreichen', unterstuetzer: [], begruendung: '',
        ...zusatz,
      }
    },
    initEingaben() {
      const neu = {}
      const neuPersonal = {}
      for (const g of this.ansicht.produktegruppen) {
        neu[g.code] = this.leererAntrag()
        neuPersonal[g.code] = this.leererAntrag({ stellen: '' })
      }
      const neuInv = {}
      for (const i of this.ansicht.investitionen) { neuInv[i.id] = this.leererAntrag() }
      this.neu = neu
      this.neuPersonal = neuPersonal
      this.neuInv = neuInv
    },
    // Antragsteller-Auswahl (F94): bei eigenen Anträgen die aktiven Mitglieder,
    // bei fremden die aktiven Fraktionen zuoberst, dann Mitglieder.
    antragstellerOptionen(herkunft) {
      const personen = (this.mitglieder || [])
        .filter(m => m && m.aktiv !== false && m.name)
        .map(m => ({ value: m.name, label: m.name }))
      const fraktionen = (this.fraktionen || [])
        .filter(f => f && f.aktiv !== false && f.name)
        .map(f => ({ value: f.name, label: f.name }))
      return herkunft === 'fremde' ? [...fraktionen, ...personen] : personen
    },
    haltungOptionen(herkunft) {
      return herkunft === 'fremde' ? HALTUNG_FREMD : HALTUNG_EIGEN
    },
    // Wechsel der Herkunft setzt die Haltung auf den passenden Standard zurück.
    herkunftGewechselt(form, wert) {
      form.herkunft = wert
      form.haltung = wert === 'fremde' ? 'offen' : 'einreichen'
      if (wert === 'fremde') { form.antragsteller = '' }
    },
    formOeffnen(key) {
      this.formOffen = { ...this.formOffen, [key]: true }
    },
    formSchliessen(key) {
      const o = { ...this.formOffen }
      delete o[key]
      this.formOffen = o
    },
    // Notizen erst mounten, wenn der Nutzer sie aufklappt (Performance).
    notizenToggle(id, event) {
      this.offeneNotizen = { ...this.offeneNotizen, [id]: !!(event && event.target && event.target.open) }
    },
    ladeVerzoegert() {
      if (this.ladeTimer) { clearTimeout(this.ladeTimer) }
      this.ladeTimer = setTimeout(() => this.ladeAnsicht(false), 350)
    },
    jahrGewechselt(opt) {
      this.jahrOption = opt
      this.ladeAnsicht()
    },
    kommissionGewechselt(opt) {
      this.kommissionOption = opt
      // Verknüpfung (F76): die Departement-Auswahl gilt nur innerhalb der Kommission.
      this.departementOption = null
      this.ladeAnsicht(false)
    },
    sitzungsmodusUmschalten(wert) {
      this.sitzungsmodus = wert
      this.ladeAnsicht(false)
    },
    handleRealtime(event) {
      if ((event?.type || '') === 'budget.updated') { this.ladeAnsicht(false) }
    },
    // Gemeinsame Antragsfelder aus dem Formular (F94/F97/F98).
    gemeinsameFelder(form) {
      return {
        herkunft: form.herkunft,
        haltung: form.haltung,
        antragsteller: form.antragsteller,
        begruendung: form.begruendung,
        unterstuetzer: this.unterstuetzerMit(form),
      }
    },
    // F98: unterstützen wir den Antrag (eigen «einreichen» / fremd «unterstuetzen»),
    // ist die eigene Fraktion automatisch in der Liste der Unterstützer.
    unterstuetzerMit(form) {
      const liste = Array.isArray(form.unterstuetzer) ? form.unterstuetzer.map(u => (u && u.value) || u) : []
      const unterstuetzt = form.haltung === 'einreichen' || form.haltung === 'unterstuetzen'
      if (unterstuetzt && this.eigeneFraktionName && !liste.includes(this.eigeneFraktionName)) {
        liste.unshift(this.eigeneFraktionName)
      }
      return liste
    },
    // Vorzeichenbehafteter CHF- und Prozentwert aus dem Formular (F95): die
    // Richtung kommt vom Umschalter (Reduktion −, Mehrausgabe +).
    signierterBetrag(form) {
      const sign = form.mehrausgabe ? 1 : -1
      const out = {}
      if (form.betrag !== '' && form.betrag !== null) { out.betragDelta = sign * Math.round(Math.abs(Number(form.betrag))) }
      if (form.prozent !== '' && form.prozent !== null) { out.prozentDelta = sign * Math.abs(Number(form.prozent)) }
      return out
    },
    async antragGlobalbudget(code) {
      const e = this.neu[code]
      const betrag = this.signierterBetrag(e)
      if (betrag.betragDelta === undefined && betrag.prozentDelta === undefined) { return }
      await this.antragSenden({ bereich: 'globalbudget', zielTyp: 'produktegruppe', zielRef: code, ...betrag, ...this.gemeinsameFelder(e) })
    },
    async antragPersonal(code) {
      const e = this.neuPersonal[code]
      const stellen = Number(e.stellen)
      if (!stellen) { return }
      const daten = { bereich: 'personal', zielTyp: 'produktegruppe', zielRef: code, stellenDelta: stellen, ...this.gemeinsameFelder(e) }
      const betrag = this.signierterBetrag(e)
      if (betrag.betragDelta !== undefined) { daten.betragDelta = betrag.betragDelta }
      await this.antragSenden(daten)
    },
    async antragInvestition(inv) {
      const e = this.neuInv[inv.id]
      const betrag = this.signierterBetrag(e)
      if (betrag.betragDelta === undefined && betrag.prozentDelta === undefined) { return }
      await this.antragSenden({ bereich: 'investition', zielTyp: 'investition', zielRef: String(inv.id), ...betrag, ...this.gemeinsameFelder(e) })
    },
    async antragSenden(daten) {
      try {
        // In der Sitzung entstehen offizielle Sitzungsanträge (F93), sonst Fraktionsanträge.
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege'), { ...daten, phase: this.aktivePhase })
        this.formOffen = {}
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Antrag speichern fehlgeschlagen', f)
      }
    },
    async antragLoeschen(id) {
      try {
        await axios.delete(generateUrl('/apps/parlwin/budget/antraege/' + id))
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Antrag löschen fehlgeschlagen', f)
      }
    },
    async verteilungAendern(feld, wert) {
      const v = { ...this.verteilung }
      v[feld] = wert
      await this.verteilungSenden(v.automatikEin, v.zielModus, Number(this.zielBetrag) || 0)
    },
    verteilungZielBetrag() {
      this.verteilungSenden(this.verteilung.automatikEin, this.verteilung.zielModus, Number(this.zielBetrag) || 0)
    },
    async pauschalVerteilen() {
      await this.verteilungSenden(true, this.verteilung.zielModus, Number(this.zielBetrag) || 0)
    },
    async verteilungSenden(automatikEin, zielModus, zielBetrag, extra = {}) {
      const haltung = extra.haltung !== undefined ? extra.haltung : (this.verteilung.haltung || 'einreichen')
      const ausnahmen = extra.ausnahmen !== undefined ? extra.ausnahmen : this.pauschalAusnahmen
      try {
        const { data } = await axios.put(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/verteilung'), { automatikEin, zielModus, zielBetrag, haltung, ausnahmen })
        this.ansicht = data
        this.initEingaben()
      } catch (f) {
        console.error('Verteilung setzen fehlgeschlagen', f)
      }
    },
    // F100: den Einreichen-Entscheid des Pauschalantrags umschalten.
    async pauschalEinreichenUmschalten(einreichen) {
      await this.verteilungSenden(this.verteilung.automatikEin, this.verteilung.zielModus, Number(this.zielBetrag) || 0, { haltung: einreichen ? 'einreichen' : 'nicht_einreichen' })
    },
    // F101: eine Position vom Pauschalantrag ausnehmen oder wieder aufnehmen.
    async ausnahmeUmschalten(code, aus) {
      const ausnahmen = [...this.pauschalAusnahmen]
      const i = ausnahmen.indexOf(code)
      if (aus && i < 0) { ausnahmen.push(code) }
      if (!aus && i >= 0) { ausnahmen.splice(i, 1) }
      await this.verteilungSenden(this.verteilung.automatikEin, this.verteilung.zielModus, Number(this.zielBetrag) || 0, { ausnahmen })
    },
    // F100: einen weiteren (festen) Pauschalantrag anlegen, ändern, löschen.
    async pauschalErstellen() {
      try {
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/pauschal'), {})
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Pauschalantrag anlegen fehlgeschlagen', f)
      }
    },
    async pauschalAendern(id, felder) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/pauschal/' + id), felder)
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Pauschalantrag ändern fehlgeschlagen', f)
      }
    },
    async pauschalLoeschen(id) {
      try {
        await axios.delete(generateUrl('/apps/parlwin/budget/pauschal/' + id))
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Pauschalantrag löschen fehlgeschlagen', f)
      }
    },
    async entscheidSetzen(id, status) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + id + '/entscheid'), { status })
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Entscheid setzen fehlgeschlagen', f)
      }
    },
    haltungOptFor(a) {
      return this.haltungOptionen(a.herkunft).find(o => o.value === a.haltung) || this.haltungOptionen(a.herkunft)[0]
    },
    // Unsere Haltung an einem bestehenden Antrag ändern (F97), getrennt vom
    // Sitzungs-Beschluss.
    async antragHaltung(id, haltung) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + id), { haltung })
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Haltung ändern fehlgeschlagen', f)
      }
    },
    // Mögliche Verknüpfungen eines Sitzungsantrags: Vorbereitungsanträge auf
    // derselben Position (F104).
    verknuepfungsKandidaten(a) {
      const alle = this.ansicht ? this.ansicht.antraege : []
      return alle
        .filter(x => (x.phase || 'fraktion') === 'fraktion' && x.bereich === a.bereich && String(x.zielRef) === String(a.zielRef))
        .map(x => ({ value: x.id, label: (x.antragsteller || 'Antrag') + ' ' + this.fr(x.betragDelta) }))
    },
    verknuepfungOpt(a) {
      if (!a.verknuepftMitId) { return null }
      return this.verknuepfungsKandidaten(a).find(o => o.value === a.verknuepftMitId) || { value: a.verknuepftMitId, label: 'verknüpft' }
    },
    // Verknüpfung von Hand setzen oder lösen (F104).
    async verknuepfungWaehlen(antragId, zielId) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + antragId + '/verknuepfung'), { zielId: zielId || 0 })
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Verknüpfung setzen fehlgeschlagen', f)
      }
    },
    steuerfussAutomatikUmschalten(ein) {
      if (ein) {
        // Automatik wieder ein: manuellen Modus verlassen, ein Steuerfuss-Antrag entfällt.
        this.steuerfussManuellModus = false
        if (this.steuerfussAntrag) { this.antragLoeschen(this.steuerfussAntrag.id) }
      } else {
        // Automatik aus: manueller Modus, damit das Eingabefeld erscheint (F88).
        this.steuerfussManuellModus = true
        this.steuerfussManuell = String(this.steuerfussEffektiv)
      }
    },
    async steuerfussAntragStellen() {
      const neuerFuss = Number(this.steuerfussManuell)
      const j = this.ansicht.jahr
      if (!neuerFuss || !j.steuerfuss) { return }
      // F96: der Steuerfuss-Antrag wird in Prozentpunkten gestellt; den CHF-Effekt
      // auf den Ertrag rechnet der Server (Ertrag × Prozentpunkte / Steuerfuss).
      const prozentDelta = neuerFuss - j.steuerfuss
      try {
        if (this.steuerfussAntrag) {
          await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + this.steuerfussAntrag.id), { prozentDelta })
        } else {
          await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege'), { bereich: 'steuerfuss', zielTyp: 'steuerfuss', prozentDelta, begruendung: 'Steuerfuss auf ' + neuerFuss + '%' })
        }
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Steuerfuss-Antrag fehlgeschlagen', f)
      }
    },
    pdfOeffnen() {
      const params = this.departementOption ? '?kommission=' + encodeURIComponent(this.departementOption.value) : ''
      window.open(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege-pdf') + params, '_blank')
    },
    async neuOeffnen() {
      this.neuJahrOption = null
      this.neuMitNovemberbrief = false
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/budget/verfuegbar'))
        this.importierbareJahre = Array.isArray(data?.importierbar) ? data.importierbar : []
        if (this.importierbareJahre.length) {
          this.neuJahrOption = { value: this.importierbareJahre[0], label: String(this.importierbareJahre[0]) }
        }
      } catch (f) {
        console.error('Verfügbare Jahre laden fehlgeschlagen', f)
        this.importierbareJahre = []
      }
      this.neuOffen = true
    },
    async jahrImportieren() {
      if (!this.neuJahrOption) { return }
      const jahr = this.neuJahrOption.value
      this.neuOffen = false
      try {
        await axios.post(generateUrl('/apps/parlwin/budget/' + jahr + '/import'), { mitNovemberbrief: this.neuMitNovemberbrief })
        const { data } = await axios.get(generateUrl('/apps/parlwin/budget/jahre'))
        this.jahre = Array.isArray(data) ? data : []
        this.jahrOption = { value: jahr, label: String(jahr) }
        await this.ladeAnsicht()
      } catch (f) {
        console.error('Budgetjahr importieren fehlgeschlagen', f)
      }
    },
    async novemberbriefEinlesen() {
      try {
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/novemberbrief'), {})
        await this.ladeAnsicht(false)
      } catch (f) {
        console.error('Novemberbrief einlesen fehlgeschlagen', f)
      }
    },
  },
}
</script>

<style scoped lang="scss">
/* Nur budget-eigene Elemente (Summenzeile, Tabs, Antragszeilen). View-Rahmen,
   Header, Filter, Karten und Modal stammen aus dem gemeinsamen Stylesheet. */
.pw-budget-summen {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding: 0.6rem 0.9rem;
  margin-block-end: 0.75rem;
  background: var(--color-main-background);
  border-block-end: 2px solid var(--color-border);
}
.pw-summe { display: flex; flex-direction: column; min-inline-size: 8rem; }
.pw-summe-label { font-size: 0.8rem; color: var(--color-text-maxcontrast); text-transform: uppercase; letter-spacing: 0.03em; }
.pw-summe-wert { font-size: 1.1rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.pw-summe-diff { font-size: 0.8rem; font-variant-numeric: tabular-nums; }
.pw-positiv, .pw-diff-plus { color: var(--color-success, #2d7d46); }
.pw-negativ, .pw-diff-minus { color: var(--color-error, #c0392b); }

.pw-budget-tabs { display: flex; gap: 0.25rem; border-block-end: 1px solid var(--color-border); margin-block-end: 0.75rem; }
.pw-budget-tab {
  border: 0;
  background: transparent;
  padding: 0.5rem 1rem;
  font-size: 0.95rem;
  cursor: pointer;
  color: var(--color-text-maxcontrast);
  border-block-end: 2px solid transparent;
}
.pw-budget-tab-aktiv { color: var(--color-main-text); font-weight: 600; border-block-end-color: var(--color-primary-element); }
.pw-budget-tabpanel { display: flex; flex-direction: column; gap: 1rem; }
.pw-budget-dep-titel { font-size: 1.05rem; margin: 0.5rem 0 0.4rem; color: var(--color-primary-element); }
.pw-budget-betrag { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: end; }
.pw-budget-antraege { list-style: none; margin: 0.4rem 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-budget-antraege li { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.9rem; }
.pw-antrag-auto { opacity: 0.8; font-style: italic; }
.pw-antrag-betrag { font-variant-numeric: tabular-nums; min-inline-size: 6rem; }
.pw-antrag-steller { font-weight: 600; }
.pw-antrag-begruendung { color: var(--color-text-maxcontrast); flex: 1 1 12rem; }
.pw-antrag-entscheid { font-size: 0.8rem; padding: 0.05rem 0.4rem; border-radius: 0.4rem; }
.pw-entscheid-angenommen { background: var(--color-success, #2d7d46); color: #fff; }
.pw-entscheid-abgelehnt { background: var(--color-error, #c0392b); color: #fff; }
.pw-entscheid-offen { background: var(--color-background-dark); }
.pw-entscheid-knoepfe { display: flex; gap: 0.15rem; }
.pw-antrag-haltung { min-inline-size: 10rem; max-inline-size: 14rem; }
.pw-antrag-verknuepfen { min-inline-size: 12rem; max-inline-size: 16rem; }
.pw-antrag-notizen { flex-basis: 100%; font-size: 0.85rem; margin-block-start: 0.2rem; }
.pw-antrag-notizen > summary { cursor: pointer; color: var(--color-text-maxcontrast); }
.pw-verteilung-ziel { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; margin-block-start: 0.4rem; }
.pw-verteilung { display: flex; flex-direction: column; gap: 0.5rem; }
.pw-steuerfuss { display: flex; flex-direction: column; gap: 0.6rem; max-inline-size: 32rem; }
.pw-budget-info { font-size: 0.9rem; color: var(--color-text-maxcontrast); }
.pw-budget-info li { display: flex; justify-content: space-between; gap: 0.5rem; }
.pw-produkt-kosten { color: var(--color-text-maxcontrast); white-space: nowrap; }
.pw-modal-aktionen { display: flex; gap: 0.5rem; justify-content: flex-end; padding: 0.75rem 1rem; }
</style>
