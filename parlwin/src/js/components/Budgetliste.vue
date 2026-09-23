<template>
  <!-- Filter im Navigations-Slot — wie alle anderen Ansichten (kein eigenes
       Filterband auf der Seite). -->
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <FilterPanel @reset="filterZuruecksetzen">
      <NcSelect v-if="jahrOptionen.length" v-model="jahrOption" :options="jahrOptionen" :clearable="false" input-label="Budgetjahr" @update:model-value="jahrGewechselt" />
      <NcSelect v-if="kommissionOptionen.length" v-model="kommissionOption" :options="kommissionOptionen" input-label="Zuständige Kommission" placeholder="Alle" @update:model-value="kommissionGewechselt" />
      <NcSelect v-model="departementOption" :options="departementOptionen" input-label="Departement" placeholder="Alle" @update:model-value="ladeAnsicht(false)" />
      <NcTextField v-model="minProzent" type="number" label="Anstieg ab %" />
      <NcTextField v-model="minAbsolut" type="number" label="Anstieg ab CHF" />
      <NcSelect v-model="antragsartOption" :options="antragsartOptionen" :clearable="false" input-label="Anträge" />
      <!-- Nur bei den Investitionen: viele Vorhaben beginnen erst in einem
           Planjahr und führen im Budgetjahr eine Null. Auf den anderen Tabs
           wirkt der Filter nicht und steht deshalb auch nicht da. -->
      <NcCheckboxRadioSwitch
        v-if="aktiverTab === 'investition'"
        v-model="nurMitBetrag"
        type="switch"
        data-filter="nur-mit-betrag"
      >
        Nur mit Betrag im Budgetjahr ({{ investitionenMitBetrag }} von {{ investitionenTotal }})
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch :model-value="sitzungsmodus" type="switch" @update:model-value="sitzungsmodusUmschalten">
        Sitzungsmodus
      </NcCheckboxRadioSwitch>
    </FilterPanel>
  </Teleport>

  <section class="pw-view-content pw-budget">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Budget</h2>
      <span class="pw-view-count">{{ produktegruppenCount }}</span>
      <NcButton v-if="novemberbriefMoeglich" type="secondary" @click="novemberbriefEinlesen">Novemberbrief einlesen</NcButton>
      <NcButton v-if="sitzungsmodus && ansicht" type="secondary" :disabled="sitzungsantraegeLaeuft" @click="sitzungsantraegeEinlesen">Sitzungsanträge einlesen</NcButton>
      <NcButton v-if="ansicht" type="tertiary" @click="reimportOeffnen">Budget neu einlesen</NcButton>
      <NcButton type="primary" @click="neuOeffnen">+ Neu</NcButton>
      <!-- Link zur Weisung ganz rechts, nach den Buttons. -->
      <a v-if="weisungQuelle" class="pw-weisung-link pw-weisung-rechts" :href="weisungQuelle" target="_blank" rel="noopener noreferrer">Weisung {{ weisungNummer }}</a>
    </header>

    <!-- Fortschritt beim Einlesen der Sitzungsanträge (F90): das Drehbuch wird live
         geladen und geparst, das dauert einige Sekunden. -->
    <div v-if="sitzungsantraegeLaeuft" class="pw-reimport-fortschritt">
      <span class="pw-reimport-label">Sitzungsanträge werden aus dem Drehbuch eingelesen…</span>
      <div class="pw-reimport-bar" role="progressbar" aria-label="Sitzungsanträge werden eingelesen">
        <div class="pw-reimport-bar-inner"></div>
      </div>
    </div>

    <!-- Fortschritt beim Neu-Einlesen (F91): das Budgetbuch wird geparst, das dauert. -->
    <div v-if="reimportLaeuft" class="pw-reimport-fortschritt">
      <span class="pw-reimport-label">Budget wird neu eingelesen…</span>
      <div class="pw-reimport-bar" role="progressbar" aria-label="Budget wird neu eingelesen">
        <div class="pw-reimport-bar-inner"></div>
      </div>
    </div>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>
    <NcEmptyContent v-else-if="!ansicht" name="Kein Budgetjahr vorhanden" description="Über «+ Neu» ein vergangenes Budgetjahr importieren." />

    <template v-else>
      <!-- Übersicht (F102): Vergleich Stadtratsbudget (wie vorgelegt) mit dem
           Fraktionsbudget (mit unseren Anträgen); die Differenz zeigt, was unsere
           Anträge bewirken. Richtet sich nach den Filtern. -->
      <!-- Übersicht und Tabs kleben zusammen als EIN Sticky-Block am oberen Rand:
           so überlagern sich die beiden nicht, und die Tabs hängen unten an den
           Übersichtszahlen (statt zwei konkurrierende Stickies auf top: 0). -->
      <div class="pw-budget-sticky">
      <div class="pw-budget-summen">
        <table class="pw-budget-vergleich">
          <thead>
            <tr>
              <th class="pw-vergleich-zeile"></th>
              <th>Ausgaben</th>
              <th>Einnahmen</th>
              <th>{{ ergebnisLabelFraktion }}</th>
              <th>Steuerfuss</th>
              <th>Stellen</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th class="pw-vergleich-zeile">Stadtratsbudget</th>
              <td data-label="Ausgaben">{{ fr(summenStadtrat.ausgaben) }}</td>
              <td data-label="Einnahmen">{{ fr(summenStadtrat.einnahmen) }}</td>
              <td :data-label="ergebnisLabelFraktion" :class="summenStadtrat.ergebnis < 0 ? 'pw-negativ' : 'pw-positiv'">{{ fr(summenStadtrat.ergebnis) }}</td>
              <td data-label="Steuerfuss">{{ ansicht.jahr.steuerfuss }}%</td>
              <td data-label="Stellen">{{ zahl(summenStadtrat.stellen) }}</td>
            </tr>
            <tr>
              <th class="pw-vergleich-zeile">Fraktionsbudget</th>
              <td data-label="Ausgaben">{{ fr(summen.ausgaben) }}</td>
              <td data-label="Einnahmen">{{ fr(summen.einnahmen) }}</td>
              <td :data-label="ergebnisLabelFraktion" :class="summen.ergebnis < 0 ? 'pw-negativ' : 'pw-positiv'">{{ fr(summen.ergebnis) }}</td>
              <td data-label="Steuerfuss">{{ steuerfussEffektiv }}%</td>
              <td data-label="Stellen">{{ zahl(summen.stellen) }}</td>
            </tr>
            <tr class="pw-vergleich-differenz">
              <th class="pw-vergleich-zeile">Differenz</th>
              <td data-label="Ausgaben" :class="diffKlasse(summen.ausgaben - summenStadtrat.ausgaben)">{{ diff(summen.ausgaben - summenStadtrat.ausgaben) }}</td>
              <td data-label="Einnahmen" :class="diffKlasse(summen.einnahmen - summenStadtrat.einnahmen)">{{ diff(summen.einnahmen - summenStadtrat.einnahmen) }}</td>
              <td :data-label="ergebnisLabelFraktion" :class="diffKlasse(summen.ergebnis - summenStadtrat.ergebnis)">{{ diff(summen.ergebnis - summenStadtrat.ergebnis) }}</td>
              <td data-label="Steuerfuss">{{ steuerfussEffektiv - ansicht.jahr.steuerfuss === 0 ? '±0%' : (steuerfussEffektiv - ansicht.jahr.steuerfuss) + '%' }}</td>
              <td data-label="Stellen" :class="diffKlasse(summen.stellen - summenStadtrat.stellen)">{{ diff(summen.stellen - summenStadtrat.stellen, true) }}</td>
            </tr>
          </tbody>
        </table>
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
          @click="tabWechseln(t.key)"
        >{{ t.label }}</button>
      </div>
      </div>

      <!-- Tab: Globalbudgets -->
      <div v-show="aktiverTab === 'globalbudget'" class="pw-budget-tabpanel">
        <!-- Pauschalanträge (F100): alle gleich behandelt, jeder mit eigenem Ziel-Typ
             (Einsparungen, schwarze Null, fester Ertrag, festes Defizit). Die Liste
             startet leer; «+ Pauschalantrag» legt einen neuen an. -->
        <div v-if="!sitzungsmodus" class="pw-data-card pw-pauschalantraege">
          <h3 class="pw-pauschal-titel">Pauschalanträge</h3>
          <BudgetPauschalForm
            v-for="p in pauschalantraege"
            :key="p.id"
            :pauschal="p"
            :produktegruppen="ansicht.produktegruppen"
            :fraktion-optionen="fraktionOptionen"
            :eigene-fraktion="eigeneFraktionName"
            @save="felder => pauschalAendern(p.id, felder)"
            @delete="pauschalLoeschen(p.id)"
          />
          <NcButton type="secondary" @click="pauschalErstellen">+ Pauschalantrag</NcButton>
        </div>

        <div v-for="dep in gruppenNachDepartement" :key="dep.name" class="pw-budget-departement">
          <h3 class="pw-budget-dep-titel">{{ dep.name }}</h3>
          <div class="pw-card-grid">
            <article v-for="g in dep.gruppen" :key="g.id" class="pw-data-card" :class="{ 'pw-budget-kuenstlich': g.kuenstlich }">
              <div class="pw-data-card-header pw-budget-kopf">
                <div class="pw-budget-kopf-titel">
                  <p class="pw-data-card-kicker">{{ g.kuenstlich ? 'Rechnerische Position' : 'Produktegruppe ' + g.code }}</p>
                  <h3>{{ g.name }}</h3>
                </div>
                <!-- F116: Wirken unsere Anträge auf die Produktegruppe, steht oben
                     der Wert des Stadtrats und darunter der Wert der Fraktion —
                     jeder mit seiner Differenz zum Vorjahr. -->
                <span class="pw-budget-betrag">
                  <span v-for="w in betragswerte(g)" :key="w.rolle" class="pw-budget-wert">
                    <span v-if="w.label" class="pw-budget-wert-label">{{ w.label }}</span>
                    <span class="pw-budget-wert-zahl">{{ fr(w.wert) }}</span>
                    <span class="pw-summe-diff" :class="diffKlasse(w.wert - w.vorjahr)">{{ diff(w.wert - w.vorjahr) }}</span>
                  </span>
                </span>
              </div>
              <ul v-if="antraegeFuer(g.code).length" class="pw-budget-antraege">
                <!-- F117: Ein Klick in den freien Bereich der Zeile macht den Antrag
                     bearbeitbar; die Bedienelemente darin behalten ihre eigene
                     Wirkung. Automatisch erzeugte Anträge (F85) bleiben unberührt. -->
                <li
                  v-for="a in antraegeFuer(g.code)"
                  :key="a.id"
                  :class="{ 'pw-antrag-auto': a.automatisch, 'pw-antrag-bearbeitbar': !a.automatisch }"
                  @click="antragBearbeiten(a, $event)"
                >
                  <BudgetAntragForm
                    v-if="bearbeitungOffen(a)"
                    :form="bearbeitung[a.id]"
                    :antragsteller-optionen="antragstellerOptionen(bearbeitung[a.id].herkunft)"
                    :fraktion-optionen="fraktionOptionen"
                    :basis="g.globalkredit.soll"
                    :zielvorgaben="g.zielvorgaben || []"
                    :kostenzeilen="g.kostenzeilen || []"
                    :produkte="g.produkte || []"
                    class="pw-antrag-bearbeiten"
                    @click.stop
                    @herkunft="herkunftGewechselt(bearbeitung[a.id], $event)"
                    @save="antragSpeichern(a)"
                    @abbrechen="antragSchliessen()"
                  />
                  <template v-else>
                    <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                    <span class="pw-antrag-steller">{{ a.antragsteller || (a.automatisch ? 'automatisch' : '') }}</span>
                    <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                    <NcCheckboxRadioSwitch v-if="!a.automatisch" :model-value="haltungAn(a)" type="switch" class="pw-antrag-toggle" @click.stop @update:model-value="haltungUmschalten(a, $event)">{{ haltungLabel(a) }}</NcCheckboxRadioSwitch>
                    <!-- Entscheid (Parlamentsbeschluss) nur in der Sitzung. -->
                    <template v-if="sitzungsmodus">
                      <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                      <span class="pw-entscheid-knoepfe">
                        <NcButton type="tertiary" :aria-label="'Antrag angenommen'" @click.stop="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                        <NcButton type="tertiary" :aria-label="'Antrag abgelehnt'" @click.stop="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                      </span>
                    </template>
                    <PwLoeschen v-if="!a.automatisch" class="pw-antrag-loeschen" label="Antrag löschen" @click.stop="antragLoeschen(a.id)" />
                    <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @click.stop @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                    <details class="pw-antrag-notizen" @click.stop @toggle="notizenToggle(a.id, $event)">
                      <summary>Notizen</summary>
                      <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                    </details>
                  </template>
                </li>
              </ul>
              <!-- F101: pro Pauschalantrag ein Ausnahme-Toggle direkt an der Produkte-
                   gruppe (zusätzlich zur Mehrfachauswahl im Pauschalantrag selbst).
                   Toggle aus = diese Position ist vom Pauschalantrag ausgenommen. -->
              <ul v-if="!g.kuenstlich && !sitzungsmodus && pauschalantraege.length" class="pw-pg-pauschale">
                <li v-for="p in pauschalantraege" :key="'ps' + p.id">
                  <NcCheckboxRadioSwitch
                    :model-value="!pgAusgenommen(p, g.code)"
                    type="switch"
                    @update:model-value="v => pgPauschalToggle(p, g.code, v)"
                  >{{ pauschalKurz(p) }}<template v-if="pgAusgenommen(p, g.code)"> — ausgenommen</template></NcCheckboxRadioSwitch>
                </li>
              </ul>
              <BudgetAntragForm
                v-if="!g.kuenstlich && formOffen['g' + g.code]"
                :form="neu[g.code]"
                :antragsteller-optionen="antragstellerOptionen(neu[g.code].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                :basis="g.globalkredit.soll"
                :zielvorgaben="g.zielvorgaben || []"
                :kostenzeilen="g.kostenzeilen || []"
                :produkte="g.produkte || []"
                @herkunft="herkunftGewechselt(neu[g.code], $event)"
                @save="antragGlobalbudget(g.code)"
                @abbrechen="formSchliessen('g' + g.code)"
              />
              <!-- Aktionszeile am Kartenfuss: «Antrag» als Hauptaktion, «Details» öffnet
                   das Vollbild-Popup (F110) mit Zielvorgaben, Produkten und Erläuterungen. -->
              <div v-if="!g.kuenstlich" class="pw-budget-card-aktionen">
                <NcButton v-if="!formOffen['g' + g.code]" type="secondary" @click="formOeffnen('g' + g.code)">+ Antrag</NcButton>
                <NcButton type="tertiary" class="pw-pg-detail-knopf" @click="pgDetailOeffnen(g.code)">
                  Details<span v-if="g.zielvorgaben && g.zielvorgaben.length"> · {{ g.zielvorgaben.length }} Zielvorgaben</span>
                </NcButton>
              </div>
            </article>
          </div>
        </div>
      </div>

      <!-- Tab: Personalbestand -->
      <div v-show="aktiverTab === 'personal'" class="pw-budget-tabpanel">
        <div v-for="dep in gruppenNachDepartement" :key="dep.name" class="pw-budget-departement">
          <h3 class="pw-budget-dep-titel">{{ dep.name }}</h3>
          <div class="pw-card-grid">
            <article v-for="g in dep.gruppen" v-show="!g.kuenstlich" :key="g.id" class="pw-data-card">
              <div class="pw-data-card-header pw-budget-kopf">
                <div class="pw-budget-kopf-titel">
                  <p class="pw-data-card-kicker">Produktegruppe {{ g.code }}</p>
                  <h3>{{ g.name }}</h3>
                </div>
                <!-- F116: dieselben zwei Zeilen wie beim Globalbudget, hier mit den Stellen. -->
                <span class="pw-budget-betrag">
                  <span v-for="w in stellenwerte(g)" :key="w.rolle" class="pw-budget-wert">
                    <span v-if="w.label" class="pw-budget-wert-label">{{ w.label }}</span>
                    <span class="pw-budget-wert-zahl">{{ zahl(w.wert) }} Stellen</span>
                    <span class="pw-summe-diff" :class="diffKlasse(w.wert - w.vorjahr)">{{ diff(w.wert - w.vorjahr, true) }}</span>
                  </span>
                </span>
              </div>
              <ul v-if="antraegeBereich('personal', g.code).length" class="pw-budget-antraege">
                <li
                  v-for="a in antraegeBereich('personal', g.code)"
                  :key="a.id"
                  :class="{ 'pw-antrag-auto': a.automatisch, 'pw-antrag-bearbeitbar': !a.automatisch }"
                  @click="antragBearbeiten(a, $event)"
                >
                  <BudgetAntragForm
                    v-if="bearbeitungOffen(a)"
                    :form="bearbeitung[a.id]"
                    :antragsteller-optionen="antragstellerOptionen(bearbeitung[a.id].herkunft)"
                    :fraktion-optionen="fraktionOptionen"
                    mit-stellen
                    class="pw-antrag-bearbeiten"
                    @click.stop
                    @herkunft="herkunftGewechselt(bearbeitung[a.id], $event)"
                    @save="antragSpeichern(a)"
                    @abbrechen="antragSchliessen()"
                  />
                  <template v-else>
                    <span class="pw-antrag-betrag" :class="diffKlasse(a.stellenDelta)">{{ zahl(a.stellenDelta) }} Stellen</span>
                    <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                    <span class="pw-antrag-steller">{{ a.antragsteller }}</span>
                    <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                    <NcCheckboxRadioSwitch v-if="!a.automatisch" :model-value="haltungAn(a)" type="switch" class="pw-antrag-toggle" @click.stop @update:model-value="haltungUmschalten(a, $event)">{{ haltungLabel(a) }}</NcCheckboxRadioSwitch>
                    <template v-if="sitzungsmodus">
                      <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                      <span class="pw-entscheid-knoepfe">
                        <NcButton type="tertiary" aria-label="Antrag angenommen" @click.stop="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                        <NcButton type="tertiary" aria-label="Antrag abgelehnt" @click.stop="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                      </span>
                    </template>
                    <PwLoeschen v-if="!a.automatisch" class="pw-antrag-loeschen" label="Antrag löschen" @click.stop="antragLoeschen(a.id)" />
                    <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @click.stop @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                    <details class="pw-antrag-notizen" @click.stop @toggle="notizenToggle(a.id, $event)">
                      <summary>Notizen</summary>
                      <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                    </details>
                  </template>
                </li>
              </ul>
              <BudgetAntragForm
                v-if="formOffen['p' + g.code]"
                :form="neuPersonal[g.code]"
                :antragsteller-optionen="antragstellerOptionen(neuPersonal[g.code].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                mit-stellen
                @herkunft="herkunftGewechselt(neuPersonal[g.code], $event)"
                @save="antragPersonal(g.code)"
                @abbrechen="formSchliessen('p' + g.code)"
              />
              <NcButton v-else type="secondary" @click="formOeffnen('p' + g.code)">+ Antrag</NcButton>
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
              <!-- Gesamtkosten sind die Summe aller Jahre; der bewilligte Kredit
                   aus dem Anhang «Kontrolle der Investitionskredite» sagt etwas
                   anderes — was das Parlament freigegeben hat — und steht daneben. -->
              <div class="pw-data-card-grid">
                <div class="pw-data-pair"><span>Gesamtkosten</span><strong>{{ fr(gesamtkosten(i)) }}</strong></div>
                <div class="pw-data-pair"><span>bereits getätigt</span><strong>{{ fr(i.bereitsGetaetigt) }}</strong></div>
                <div class="pw-data-pair"><span>künftig</span><strong>{{ fr(i.fap1 + i.fap2 + i.fap3) }}</strong></div>
                <div v-if="i.gesamtkosten" class="pw-data-pair"><span>bewilligter Kredit</span><strong>{{ fr(i.gesamtkosten) }}</strong></div>
                <div v-if="i.planungskosten" class="pw-data-pair"><span>Planung</span><strong>{{ fr(i.planungskosten) }}</strong></div>
              </div>
              <!-- «Details» öffnet dasselbe Vollbild-Popup wie bei einer Produkte-
                   gruppe, hier mit der Jahresreihe und den bewilligten Krediten. -->
              <div class="pw-budget-card-aktionen">
                <NcButton type="tertiary" class="pw-pg-detail-knopf" @click="invDetailOeffnen(i.id)">
                  Details<span v-if="i.konten && i.konten.length"> · {{ i.konten.length }} Kredite</span>
                </NcButton>
              </div>
              <ul v-if="antraegeBereich('investition', i.id).length" class="pw-budget-antraege">
                <li
                  v-for="a in antraegeBereich('investition', i.id)"
                  :key="a.id"
                  :class="{ 'pw-antrag-auto': a.automatisch, 'pw-antrag-bearbeitbar': !a.automatisch }"
                  @click="antragBearbeiten(a, $event)"
                >
                  <BudgetAntragForm
                    v-if="bearbeitungOffen(a)"
                    :form="bearbeitung[a.id]"
                    :antragsteller-optionen="antragstellerOptionen(bearbeitung[a.id].herkunft)"
                    :fraktion-optionen="fraktionOptionen"
                    :basis="i.bu"
                    class="pw-antrag-bearbeiten"
                    @click.stop
                    @herkunft="herkunftGewechselt(bearbeitung[a.id], $event)"
                    @save="antragSpeichern(a)"
                    @abbrechen="antragSchliessen()"
                  />
                  <template v-else>
                  <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
                  <span class="pw-antrag-steller">{{ a.antragsteller }}</span>
                  <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
                  <NcCheckboxRadioSwitch v-if="!a.automatisch" :model-value="haltungAn(a)" type="switch" class="pw-antrag-toggle" @click.stop @update:model-value="haltungUmschalten(a, $event)">{{ haltungLabel(a) }}</NcCheckboxRadioSwitch>
                  <template v-if="sitzungsmodus">
                    <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
                    <span class="pw-entscheid-knoepfe">
                      <NcButton type="tertiary" aria-label="Antrag angenommen" @click.stop="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                      <NcButton type="tertiary" aria-label="Antrag abgelehnt" @click.stop="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
                    </span>
                  </template>
                  <PwLoeschen v-if="!a.automatisch" class="pw-antrag-loeschen" label="Antrag löschen" @click.stop="antragLoeschen(a.id)" />
                  <NcSelect v-if="sitzungsmodus" :model-value="verknuepfungOpt(a)" :options="verknuepfungsKandidaten(a)" input-label="Verknüpft mit" placeholder="nicht verknüpft" class="pw-antrag-verknuepfen" @click.stop @update:model-value="verknuepfungWaehlen(a.id, $event ? $event.value : 0)" />
                  <details class="pw-antrag-notizen" @click.stop @toggle="notizenToggle(a.id, $event)">
                    <summary>Notizen</summary>
                    <NotizenListe v-if="offeneNotizen[a.id]" :basis-url="'budget/antraege/' + a.id" :notizen="a.aktionen || []" :aktuelle-uid="aktuelleUid" @geaendert="ladeAnsicht(false)" />
                  </details>
                  </template>
                </li>
              </ul>
              <BudgetAntragForm
                v-if="formOffen['i' + i.id]"
                :form="neuInv[i.id]"
                :antragsteller-optionen="antragstellerOptionen(neuInv[i.id].herkunft)"
                :fraktion-optionen="fraktionOptionen"
                :basis="i.bu"
                @herkunft="herkunftGewechselt(neuInv[i.id], $event)"
                @save="antragInvestition(i)"
                @abbrechen="formSchliessen('i' + i.id)"
              />
              <NcButton v-else type="secondary" @click="formOeffnen('i' + i.id)">+ Antrag</NcButton>
            </article>
          </div>
        </div>
      </div>

      <!-- Tab: Steuerfuss -->
      <div v-show="aktiverTab === 'steuerfuss'" class="pw-budget-tabpanel">
        <div class="pw-data-card pw-steuerfuss">
          <p>Stadtratsantrag Steuerfuss: <strong>{{ ansicht.jahr.steuerfuss }}%</strong></p>
          <p v-if="steuerfussVorjahr">Geltender Steuerfuss (Vorjahr): {{ steuerfussVorjahr }}% · Differenz zum Vorjahr: <strong>{{ steuerfussDiffVorjahr }}</strong></p>
          <p v-else>Differenz zum Vorjahr: <strong>±0%</strong></p>
          <p>1 Steuerprozent ≈ {{ fr(wertProProzent) }}</p>
          <NcCheckboxRadioSwitch :model-value="steuerfussAutomatik" :disabled="steuerfussLaeuft" type="switch" @update:model-value="steuerfussAutomatikUmschalten">
            Steuerfuss bei Überschuss automatisch senken
          </NcCheckboxRadioSwitch>
          <!-- Automatik ein: die Senkung ist ein impliziter Antrag der eigenen
               Fraktion, das manuelle Feld und der Toggle bleiben ausgeblendet (F88). -->
          <p v-if="steuerfussAutomatik" class="pw-steuerfuss-auto">
            <template v-if="steuerfussAntragAuto">Automatisch gesenkt auf <strong>{{ steuerfussEffektiv }}%</strong> ({{ ansicht.jahr.steuerfuss - steuerfussEffektiv }} Prozentpunkte). Antrag der eigenen Fraktion: {{ steuerfussAntragAuto.antragsteller || 'eigene Fraktion' }}.</template>
            <template v-else>Kein Überschuss — keine automatische Senkung, es gilt der Stadtratsantrag.</template>
          </p>
          <!-- Automatik aus: Steuerfuss von Hand setzen (Standard: Stadtratsantrag).
               «Antrag stellen» ist ein Toggle, kein Knopf — der Antrag entsteht und
               verschwindet mit ihm, Mehrfachklicks erzeugen keine Duplikate. -->
          <template v-else>
            <div class="pw-verteilung-ziel">
              <NcTextField :model-value="steuerfussManuell" :disabled="steuerfussLaeuft" type="number" label="Steuerfuss %" @update:model-value="steuerfussManuellSetzen" />
            </div>
            <NcCheckboxRadioSwitch :model-value="steuerfussAntragStellenAn" :disabled="steuerfussLaeuft" type="switch" @update:model-value="steuerfussAntragStellenUmschalten">
              Antrag stellen
            </NcCheckboxRadioSwitch>
          </template>
        </div>
      </div>

      <!-- Tab: Anträge — alle Anträge der aktiven Phase zusammengefasst (nach
           Departement wie im PDF), plus der PDF-Export. So sieht man alle Anträge
           auf einen Blick, ohne das PDF zu erzeugen. -->
      <div v-show="aktiverTab === 'antraege'" class="pw-budget-tabpanel">
        <div class="pw-antraege-kopf">
          <NcCheckboxRadioSwitch :model-value="pdfMitFremden" type="switch" @update:model-value="pdfMitFremden = $event">Mit unterstützten fremden Anträgen</NcCheckboxRadioSwitch>
          <NcButton type="secondary" @click="pdfOeffnen">Anträge als PDF</NcButton>
        </div>
        <div v-if="!alleAntraege.length" class="pw-budget-info">Keine Anträge.</div>
        <!-- Ein Grid über ALLE Departemente (Subgrid je Zeile), damit die Spalten
             gruppenübergreifend fluchten: Position links, Betrag/Zusatz-Zahl rechts,
             Einheit auf gemeinsamer linker Kante, Begründung flexibel, Beschluss rechts. -->
        <div v-else class="pw-antraege-tabelle">
          <template v-for="grp in antraegeNachDepartement" :key="grp.name">
            <h3 class="pw-antraege-dep">{{ grp.name }}</h3>
            <div v-for="a in grp.antraege" :key="a.id" class="pw-antrag-zeile" :class="{ 'pw-antrag-auto': a.automatisch }">
              <span class="pw-antrag-pos">{{ a.posName }}</span>
              <span class="pw-antrag-betrag pw-num" :class="diffKlasse(a.betragDelta)">{{ a.betragDelta ? fr(a.betragDelta) : '' }}</span>
              <span class="pw-antrag-znum pw-num" :class="diffKlasse(antragZusatzWert(a))">{{ antragZusatzNum(a) }}</span>
              <span class="pw-antrag-zeinheit">{{ antragZusatzEinheit(a) }}</span>
              <span class="pw-antrag-steller">{{ a.antragsteller || (a.automatisch ? 'automatisch' : '') }}</span>
              <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
              <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
            </div>
          </template>
        </div>
      </div>

      <!-- F111: das ganze Budget als geschachtelte Kreise, Fläche = Betrag. -->
      <div v-show="aktiverTab === 'grafik'" class="pw-budget-tabpanel">
        <BudgetGrafik
          :produktegruppen="ansicht.produktegruppen"
          :jahr="ansicht.jahr ? ansicht.jahr.jahr : 0"
        />
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

    <!-- Budget neu einlesen — destruktiv, darum doppelte Absicherung: Dialog plus
         Verstanden-Checkbox; «Neu einlesen» ist gesperrt, bis die Checkbox gesetzt ist. -->
    <Teleport to="body">
      <div v-if="reimportOffen" class="pw-modal-overlay" @click.self="reimportAbbrechen">
        <div class="pw-modal">
          <div class="pw-modal-kopf">
            <h3>Budget {{ jahrOption ? jahrOption.value : '' }} neu einlesen — bist du ganz sicher?</h3>
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="reimportAbbrechen">✕</button>
          </div>
          <div class="pw-modal-body">
            <p>Das Budget wird vollständig neu aus dem Budgetbuch eingelesen. Dabei werden <strong>alle bestehenden Anträge, Notizen, Pauschalanträge und Entscheide zu diesem Budget unwiederbringlich gelöscht.</strong></p>
            <NcCheckboxRadioSwitch :model-value="reimportVerstanden" type="checkbox" @update:model-value="v => reimportVerstanden = v">
              Ich verstehe, dass alle bestehenden Anträge, Notizen, usw. zu diesem Budget dabei unwiederbringlich gelöscht werden
            </NcCheckboxRadioSwitch>
          </div>
          <div class="pw-modal-aktionen">
            <NcButton type="error" :disabled="!reimportVerstanden || reimportLaeuft" @click="reimportAusfuehren">Neu einlesen</NcButton>
            <NcButton type="tertiary" @click="reimportAbbrechen">Abbrechen</NcButton>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Vollbild-Detail einer Produktegruppe (F110): wie bei den Geschäften ein
         Popup, das den ganzen Bildschirm für eine Produktegruppe nutzt. -->
    <Teleport to="body">
      <div v-if="pgDetail" class="pw-modal-overlay" @click.self="pgDetailSchliessen">
        <div class="pw-modal pw-modal-vollbild">
          <div class="pw-modal-kopf pw-modal-kopf-leer">
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="pgDetailSchliessen">✕</button>
          </div>
          <BudgetPgDetail :gruppe="pgDetail" :jahr="ansicht && ansicht.jahr ? ansicht.jahr.jahr : 0" />
        </div>
      </div>
    </Teleport>

    <!-- Vollbild-Detail eines Investitionsprojekts (F87). -->
    <Teleport to="body">
      <div v-if="invDetail" class="pw-modal-overlay" @click.self="invDetailSchliessen">
        <div class="pw-modal pw-modal-vollbild">
          <div class="pw-modal-kopf pw-modal-kopf-leer">
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="invDetailSchliessen">✕</button>
          </div>
          <BudgetInvDetail :projekt="invDetail" :jahr="ansicht && ansicht.jahr ? ansicht.jahr.jahr : 0" />
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
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
import BudgetPgDetail from './BudgetPgDetail.vue'
import BudgetInvDetail from './BudgetInvDetail.vue'
import FilterPanel from './FilterPanel.vue'
import NotizenListe from './NotizenListe.vue'
import PwLoeschen from './PwLoeschen.vue'
import BudgetGrafik from './BudgetGrafik.vue'
import { subscribeRealtime } from '../realtime'
import { frankenFormat } from '../utils'

export default {
  name: 'Budgetliste',
  components: { NcButton, NcSelect, NcTextField, NcCheckboxRadioSwitch, NcLoadingIcon, NcEmptyContent, PwMultiSelect, BudgetAntragForm, BudgetPauschalForm, BudgetPgDetail, BudgetInvDetail, BudgetGrafik, FilterPanel, NotizenListe, PwLoeschen },
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
      // F117: der Antrag, der gerade bearbeitet wird (Antrags-ID), und sein
      // Formularstand. Es ist immer höchstens einer offen.
      bearbeiteterAntrag: null,
      bearbeitung: {},
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
      // Anträge-PDF: unterstützte fremde Anträge mit aufnehmen? (Option, F92).
      pdfMitFremden: false,
      basisTabs: [
        { key: 'globalbudget', label: 'Globalbudgets' },
        { key: 'personal', label: 'Personalbestand' },
        { key: 'investition', label: 'Investitionsrechnung' },
        { key: 'steuerfuss', label: 'Steuerfuss' },
      ],
      neu: {},
      neuPersonal: {},
      neuInv: {},
      steuerfussManuell: '',
      // Läuft gerade eine Steuerfuss-Operation? Sperrt Schalter, Feld und Toggle,
      // damit Mehrfachklicks während des (langsamen) Neurechnens keine doppelten
      // Anträge erzeugen. steuerfussTimer entprellt das Feld-Eintippen.
      steuerfussLaeuft: false,
      steuerfussTimer: null,
      // Scrollposition je Tab — nur im RAM (kein Cookie/Storage): beim Tabwechsel
      // gemerkt, bei Rückkehr wiederhergestellt, damit man nicht neu suchen muss.
      scrollProTab: {},
      // Code der Produktegruppe, deren Vollbild-Detail (F110) offen ist; null = zu.
      pgDetailCode: null,
      // Das Investitionsprojekt, dessen Detail offen ist (F87).
      invDetailId: null,
      // Blendet Investitionsprojekte aus, die im Budgetjahr nichts führen.
      nurMitBetrag: false,
      neuOffen: false,
      neuJahrOption: null,
      importierbareJahre: [],
      neuMitNovemberbrief: false,
      reimportOffen: false,
      reimportVerstanden: false,
      reimportLaeuft: false,
      sitzungsantraegeLaeuft: false,
      unsubRealtime: null,
      ladeTimer: null,
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
      const opt = (this.fraktionen || [])
        .filter(f => f && f.aktiv !== false && f.name)
        .map(f => ({ value: f.name, label: f.name }))
      // Die eigene Fraktion (Config, für die Antragsteller-Vorbelegung) muss wählbar
      // sein, auch wenn sie nicht in der Mitglieder-Fraktionsliste vorkommt.
      const eigen = this.eigeneFraktionName
      if (eigen && !opt.some(o => o.value === eigen)) { opt.unshift({ value: eigen, label: eigen }) }
      return opt
    },
    eigeneFraktionName() {
      // Autoritativ ist die Konfiguration (dieselbe Quelle wie das PDF); Rückfall auf
      // die als «eigene» markierte Fraktion in der Mitgliederliste.
      const konf = this.ansicht && this.ansicht.eigeneFraktion ? String(this.ansicht.eigeneFraktion).trim() : ''
      if (konf) { return konf }
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
    // Stadtratsbudget (F102): die Summen ohne unsere Anträge, für den Vergleich.
    summenStadtrat() { return this.ansicht && this.ansicht.summenStadtrat ? this.ansicht.summenStadtrat : this.summen },
    ergebnisLabelFraktion() { return (this.summen.ergebnis || 0) < 0 ? 'Defizit' : 'Ertrag' },
    // F100: alle Pauschalanträge (einheitlich, je mit eigenem Ziel-Typ). Die Liste
    // startet leer.
    pauschalantraege() {
      return this.ansicht && Array.isArray(this.ansicht.pauschalantraege) ? this.ansicht.pauschalantraege : []
    },
    standardBetragProStelle() {
      return this.ansicht ? this.ansicht.standardBetragProStelle : 200000
    },
    gruppenNachDepartement() {
      return this.gruppieren(this.ansicht ? this.ansicht.produktegruppen : [], g => g.departement, 'gruppen')
    },
    investitionenTotal() {
      return (this.ansicht ? this.ansicht.investitionen : []).length
    },
    investitionenMitBetrag() {
      return (this.ansicht ? this.ansicht.investitionen : []).filter(i => Number(i.bu) !== 0).length
    },
    // Ein Projekt, das erst in einem Planjahr beginnt, führt im Budgetjahr null.
    // Über solche wird jetzt nicht entschieden, deshalb lassen sie sich ausblenden.
    investitionenNachDepartement() {
      const alle = this.ansicht ? this.ansicht.investitionen : []
      const sichtbar = this.nurMitBetrag ? alle.filter(i => Number(i.bu) !== 0) : alle
      return this.gruppieren(sichtbar, i => i.departement, 'projekte')
    },
    // Alle Anträge der aktiven Phase (für das Anträge-Tab und dessen Zähler).
    alleAntraege() {
      return (this.ansicht ? this.ansicht.antraege : []).filter(a => (a.phase || 'fraktion') === this.aktivePhase)
    },
    // Tabs inkl. dynamischem «X Anträge»-Tab (X = Anzahl der Anträge).
    tabs() {
      return [
        ...this.basisTabs,
        { key: 'antraege', label: this.alleAntraege.length + ' Anträge' },
        // F111: die grafische Übersicht steht zuhinterst.
        { key: 'grafik', label: 'Grafik' },
      ]
    },
    // Alle Anträge nach Departement gruppiert, mit lesbarer Positionsbezeichnung —
    // die Zusammenfassung im Anträge-Tab (dieselbe Gliederung wie das Anträge-PDF).
    antraegeNachDepartement() {
      const pgNach = {}
      for (const g of (this.ansicht ? this.ansicht.produktegruppen : [])) { pgNach[g.code] = g }
      const invNach = {}
      for (const i of (this.ansicht ? this.ansicht.investitionen : [])) { invNach[String(i.id)] = i }
      const gruppen = new Map()
      for (const a of this.alleAntraege) {
        let dept = 'Weiteres'
        let pos = a.zielRef
        if (a.bereich === 'steuerfuss') {
          dept = 'Steuerfuss'; pos = 'Steuerfuss'
        } else if (a.bereich === 'investition') {
          const i = invNach[String(a.zielRef)]; dept = i ? i.departement : 'Investitionen'; pos = i ? i.projekt : a.zielRef
        } else {
          const g = pgNach[a.zielRef]; dept = g ? g.departement : 'Weiteres'; pos = g ? (g.name + ' (' + g.code + ')') : a.zielRef
        }
        if (!gruppen.has(dept)) { gruppen.set(dept, []) }
        gruppen.get(dept).push({ ...a, posName: pos })
      }
      return [...gruppen.entries()].map(([name, antraege]) => ({ name, antraege }))
    },
    // Die Produktegruppe des offenen Vollbild-Details (F110), oder null.
    pgDetail() {
      if (!this.pgDetailCode || !this.ansicht) { return null }
      return (this.ansicht.produktegruppen || []).find(g => g.code === this.pgDetailCode) || null
    },
    // Das Investitionsprojekt des offenen Vollbild-Details (F87), oder null.
    invDetail() {
      if (!this.invDetailId || !this.ansicht) { return null }
      return (this.ansicht.investitionen || []).find(i => i.id === this.invDetailId) || null
    },
    wertProProzent() {
      const j = this.ansicht ? this.ansicht.jahr : null
      return j && j.steuerfuss > 0 ? Math.floor(j.steuerertrag / j.steuerfuss) : 0
    },
    // Steuerfuss des Vorjahres (falls importiert) und die Differenz zum beantragten
    // Steuerfuss (F88).
    steuerfussVorjahr() {
      return this.ansicht && this.ansicht.jahr ? (this.ansicht.jahr.steuerfussVorjahr || 0) : 0
    },
    steuerfussDiffVorjahr() {
      const d = (this.ansicht && this.ansicht.jahr ? this.ansicht.jahr.steuerfuss : 0) - this.steuerfussVorjahr
      return (d > 0 ? '+' : (d < 0 ? '−' : '±')) + Math.abs(d) + '%'
    },
    // Ein von Hand gestellter Steuerfuss-Antrag (quelle ≠ «pauschal»); existiert nur,
    // wenn die Automatik aus und «Antrag stellen» ein ist.
    steuerfussAntrag() {
      return (this.ansicht ? this.ansicht.antraege : []).find(a => a.bereich === 'steuerfuss' && a.quelle !== 'pauschal') || null
    },
    // Der automatisch erzeugte Steuerfuss-Antrag (F88), der den Überschuss senkt.
    steuerfussAntragAuto() {
      return (this.ansicht ? this.ansicht.antraege : []).find(a => a.bereich === 'steuerfuss' && a.quelle === 'pauschal') || null
    },
    // F88: der Automatik-Schalter kommt vom Server (pro Jahr gespeichert), nicht mehr
    // aus einem flüchtigen Kennzeichen im Browser — so überlebt der Zustand das
    // Neuladen.
    steuerfussAutomatik() { return !!(this.ansicht && this.ansicht.jahr && this.ansicht.jahr.steuerfussAutomatik) },
    // «Antrag stellen»-Toggle: an, sobald ein manueller Steuerfussantrag existiert.
    steuerfussAntragStellenAn() { return this.steuerfussAntrag !== null },
    steuerfussEffektiv() {
      const j = this.ansicht ? this.ansicht.jahr : null
      if (!j) { return 0 }
      // Massgeblich ist der gespeicherte Antrag (das gerenderte Ergebnis ist die
      // Wahrheit), nicht der noch nicht übernommene Feldwert.
      if (this.steuerfussAntrag) {
        return j.steuerfuss + Math.round(this.steuerfussAntrag.prozentDelta || 0)
      }
      // F88: die automatische Senkung ist ein echter Antrag; sein Prozentpunkt-Delta
      // ist massgeblich (der Überschuss in den Summen ist dadurch bereits reduziert).
      if (this.steuerfussAntragAuto) {
        return j.steuerfuss + Math.round(this.steuerfussAntragAuto.prozentDelta || 0)
      }
      return j.steuerfuss
    },
    novemberbriefMoeglich() {
      // F91: der Knopf erscheint nur, wenn tatsächlich ein noch nicht eingelesener,
      // mindestens zwei Tage alter Novemberbrief vorliegt (vom Server bestimmt) —
      // im August ist er typischerweise noch gar nicht da.
      return !!(this.ansicht && this.ansicht.jahr && this.ansicht.jahr.novemberbriefVerfuegbar)
    },
    // F89: Link zum Budget-Geschäft (Weisung) auf der Parlamentswebseite.
    weisungQuelle() {
      return this.ansicht && this.ansicht.jahr ? (this.ansicht.jahr.weisungQuelle || '') : ''
    },
    weisungNummer() {
      return this.ansicht && this.ansicht.jahr ? (this.ansicht.jahr.weisungNummer || '') : ''
    },
  },
  watch: {
    minProzent() { this.ladeVerzoegert() },
    minAbsolut() { this.ladeVerzoegert() },
  },
  mounted() {
    this.ladeJahre()
    this.unsubRealtime = subscribeRealtime(this.handleRealtime)
    this.$nextTick(() => { this.filterReady = true })
  },
  beforeUnmount() {
    if (this.unsubRealtime) { this.unsubRealtime() }
    if (this.steuerfussTimer) { clearTimeout(this.steuerfussTimer) }
  },
  methods: {
    fr: frankenFormat,
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
    // F116: die Werte im Kopf einer Karte. Ändern unsere Anträge den Wert, stehen
    // zwei Zeilen da — oben der Stadtrat, darunter die Fraktion —, sonst nur der
    // eine Wert ohne Beschriftung. Jede Zeile trägt ihr Vorjahr für die Differenz.
    kopfwerte(soll, sollFraktion, vorjahr) {
      const stadtrat = Number(soll) || 0
      const fraktion = sollFraktion === undefined || sollFraktion === null ? stadtrat : Number(sollFraktion)
      const vj = Number(vorjahr) || 0
      if (fraktion === stadtrat) {
        return [{ rolle: 'stadtrat', label: '', wert: stadtrat, vorjahr: vj }]
      }
      return [
        { rolle: 'stadtrat', label: 'Stadtrat', wert: stadtrat, vorjahr: vj },
        { rolle: 'fraktion', label: 'Fraktion', wert: fraktion, vorjahr: vj },
      ]
    },
    betragswerte(g) {
      return this.kopfwerte(g.globalkredit.soll, g.globalkredit.sollFraktion, g.globalkredit.sollVorjahr)
    },
    stellenwerte(g) {
      return this.kopfwerte(g.stellen.soll, g.stellen.sollFraktion, g.stellen.sollVorjahr)
    },
    entscheidLabel(e) {
      return { angenommen: 'angenommen', abgelehnt: 'abgelehnt' }[e] || 'offen'
    },
    // Zusatzgrösse eines Antrags fürs Anträge-Tab (neben dem CHF-Betrag): Stellen
    // oder Steuerprozentpunkte. Zahl und Einheit getrennt, damit sie im Grid je auf
    // ihrer eigenen Kante fluchten (Zahl rechts, Einheit links). Wert für die Farbe.
    antragZusatzWert(a) {
      if (a.stellenDelta) { return a.stellenDelta }
      if (a.prozentDelta && a.bereich === 'steuerfuss') { return a.prozentDelta }
      return 0
    },
    antragZusatzNum(a) {
      if (a.stellenDelta) { return this.zahl(a.stellenDelta) }
      if (a.prozentDelta && a.bereich === 'steuerfuss') { return (a.prozentDelta > 0 ? '+' : '−') + Math.abs(a.prozentDelta) }
      return ''
    },
    antragZusatzEinheit(a) {
      if (a.stellenDelta) { return 'Stellen' }
      if (a.prozentDelta && a.bereich === 'steuerfuss') { return '%' }
      return ''
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
        this.meldeFehler('Budgetjahre laden fehlgeschlagen', f)
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
        this.initEingaben()
      } catch (f) {
        this.meldeFehler('Budgetansicht laden fehlgeschlagen', f)
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
        // Antragsteller standardmässig die eigene Fraktion (im Rat stellt die Fraktion
        // den Antrag); die Person lässt sich weiterhin auswählen.
        herkunft: 'eigene', antragsteller: this.eigeneFraktionName || this.eigenerName,
        haltung: 'einreichen', unterstuetzer: [], begruendung: '',
        zielAenderungen: [], aufteilung: [],
        ...zusatz,
      }
    },
    /**
     * Legt für jede Position einen leeren Formularstand bereit — aber NUR, wo
     * noch keiner steht.
     *
     * Die Ansicht lädt im Hintergrund neu: nach jedem gespeicherten Antrag, auf
     * eine Echtzeit-Meldung hin, nach einem Filter- oder Phasenwechsel. Wurden
     * die Stände dabei ersetzt, verlor der Benutzer mitten im Tippen alles
     * Eingegebene, ohne jede Meldung — das Feld stand plötzlich wieder leer da.
     */
    /** Verwirft alle Formularstände — nach dem Speichern, wo nichts mehr offen ist. */
    eingabenLeeren() {
      this.neu = {}
      this.neuPersonal = {}
      this.neuInv = {}
      this.initEingaben()
    },
    initEingaben() {
      const neu = { ...this.neu }
      const neuPersonal = { ...this.neuPersonal }
      for (const g of this.ansicht.produktegruppen) {
        if (!neu[g.code]) { neu[g.code] = this.leererAntrag() }
        if (!neuPersonal[g.code]) { neuPersonal[g.code] = this.leererAntrag({ stellen: '' }) }
      }
      const neuInv = { ...this.neuInv }
      for (const i of this.ansicht.investitionen) {
        if (!neuInv[i.id]) { neuInv[i.id] = this.leererAntrag() }
      }
      this.neu = neu
      this.neuPersonal = neuPersonal
      this.neuInv = neuInv
      this.steuerfussManuellSync()
    },
    // Antragsteller-Auswahl (F94): bei fremden die aktiven Fraktionen zuoberst, dann
    // alle Mitglieder; bei eigenen die eigene Fraktion zuoberst (Standard) und darunter
    // ausschliesslich ihre eigenen Mitglieder — ein eigener Antrag kommt nie von einem
    // Mitglied einer anderen Fraktion. Ist keine eigene Fraktion konfiguriert, kann
    // nicht gefiltert werden; dann stehen alle Mitglieder zur Wahl.
    antragstellerOptionen(herkunft) {
      const aktive = (this.mitglieder || []).filter(m => m && m.aktiv !== false && m.name)
      const alsOption = m => ({ value: m.name, label: m.name })
      const fraktionen = (this.fraktionen || [])
        .filter(f => f && f.aktiv !== false && f.name)
        .map(f => ({ value: f.name, label: f.name }))
      if (herkunft === 'fremde') { return [...fraktionen, ...aktive.map(alsOption)] }
      const eigen = this.eigeneFraktionName
      const eigenKlein = eigen.trim().toLowerCase()
      const eigenOpt = eigen ? [{ value: eigen, label: eigen }] : []
      const personen = eigenKlein
        ? aktive.filter(m => (m.fraktion || '').trim().toLowerCase() === eigenKlein)
        : aktive
      return [...eigenOpt, ...personen.map(alsOption).filter(p => p.value !== eigen)]
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
    // Setzt die Filter zurück (Budgetjahr und Sitzungsmodus bleiben — das sind
    // Auswahl/Modus, keine Filter).
    filterZuruecksetzen() {
      this.departementOption = null
      this.kommissionOption = null
      this.minProzent = ''
      this.minAbsolut = ''
      this.antragsartOption = { value: 'alle', label: 'Alle Anträge' }
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
        zielAenderungen: form.zielAenderungen || [],
        aufteilung: form.aufteilung || [],
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
      // F109: ein reiner Zielvorgaben-Antrag oder eine reine Aufteilung (Summe wird
      // serverseitig zum PG-Betrag) ist ohne oberen Budgetwert zulässig.
      const hatZiel = (e.zielAenderungen || []).length > 0
      const hatAufteilung = (e.aufteilung || []).length > 0
      if (betrag.betragDelta === undefined && betrag.prozentDelta === undefined && !hatZiel && !hatAufteilung) { return }
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
    // Ein abgelehnter Aufruf wird dem Benutzer gemeldet, mit dem Grund, den der
    // Server nennt — bei einer zu weit gehenden Kürzung etwa das Budget, das
    // bereits Beantragte und den verbleibenden Rest (F112). Ohne diese Meldung
    // blieb das Formular offen und es geschah nichts, ohne jede Erklärung.
    meldeFehler(was, f) {
      const daten = (f && f.response && f.response.data) || {}
      const grund = daten.fehler || daten.message || (f && f.message) || ''
      showError(was + (grund ? ': ' + grund : ''))
    },
    async antragSenden(daten) {
      try {
        // In der Sitzung entstehen offizielle Sitzungsanträge (F93), sonst Fraktionsanträge.
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege'), { ...daten, phase: this.aktivePhase })
        // Gespeichertes gehört nicht mehr ins Formular: Die Stände werden hier
        // geleert, wo der Antrag wirklich angelegt wurde — nicht beim Neuladen
        // der Ansicht, das auch mitten in einer Eingabe geschehen kann.
        this.formOffen = {}
        this.eingabenLeeren()
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Antrag speichern fehlgeschlagen', f)
      }
    },
    async antragLoeschen(id) {
      try {
        await axios.delete(generateUrl('/apps/parlwin/budget/antraege/' + id))
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Antrag löschen fehlgeschlagen', f)
      }
    },
    // F117: einen bestehenden Antrag bearbeiten. Der Klick auf die Zeile öffnet
    // dasselbe Formular wie beim Anlegen, gefüllt mit dem, was am Antrag steht.
    // Automatisch erzeugte Anträge (F85) gehören dem Pauschalantrag und bleiben.
    bearbeitungOffen(a) {
      return this.bearbeiteterAntrag === a.id
    },
    // Bearbeitet wird auf einen Klick in den freien Bereich der Zeile. Ein Klick
    // auf ein Bedienelement gehört diesem Element: der Schalter schaltet, der
    // Löschknopf löscht, die Notizen klappen auf. Geprüft wird das am Ziel des
    // Klicks statt mit @click.stop an jedem Element — fremde Komponenten setzen
    // den Modifikator auf ihr Wurzelelement, und ein Klick auf ein Kindelement
    // darin erreicht die Zeile trotzdem.
    antragBearbeiten(a, event) {
      if (a.automatisch || this.bearbeiteterAntrag === a.id) { return }
      if (event && event.target && event.target.closest(
        'button, input, select, textarea, a, summary, label, .pw-antrag-toggle, .pw-antrag-loeschen, .pw-antrag-verknuepfen, .pw-antrag-notizen, .pw-entscheid-knoepfe'
      )) { return }
      this.bearbeitung = { ...this.bearbeitung, [a.id]: this.antragAlsForm(a) }
      this.bearbeiteterAntrag = a.id
    },
    antragSchliessen() {
      this.bearbeiteterAntrag = null
    },
    // Der Antrag als Formularstand: Richtung als Umschalter, Betrag und Prozent
    // als Betragswert ohne Vorzeichen (F95), alles Übrige unverändert.
    //
    // Betrag und Prozent sind zwei Schreibweisen desselben Antrags: der Dienst
    // rechnet den einen aus dem anderen. Vorbelegt wird darum nur die Grösse,
    // in der der Antrag gestellt wurde — stünden beide im Formular, ginge eine
    // Änderung am Betrag gegen den unveränderten Prozentsatz verloren (F117).
    antragAlsForm(a) {
      const betrag = Number(a.betragDelta) || 0
      const prozent = Number(a.prozentDelta) || 0
      const stellen = Number(a.stellenDelta) || 0
      return {
        betrag: betrag ? String(Math.abs(betrag)) : '',
        prozent: betrag ? '' : (prozent ? String(Math.abs(prozent)) : ''),
        stellen: stellen ? String(stellen) : '',
        mehrausgabe: betrag > 0 || (betrag === 0 && prozent > 0),
        herkunft: a.herkunft || 'eigene',
        antragsteller: a.antragsteller || '',
        haltung: a.haltung || 'einreichen',
        unterstuetzer: (a.unterstuetzer || []).map(u => (u && u.name) || u),
        begruendung: a.begruendung || '',
        zielAenderungen: (a.zielAenderungen || []).map(z => ({ ...z })),
        aufteilung: (a.aufteilung || []).map(x => ({ ...x })),
      }
    },
    async antragSpeichern(a) {
      const form = this.bearbeitung[a.id]
      if (!form) { return }
      const daten = { ...this.signierterBetrag(form), ...this.gemeinsameFelder(form) }
      if (a.bereich === 'personal') { daten.stellenDelta = Number(form.stellen) || 0 }
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + a.id), daten)
        this.antragSchliessen()
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Antrag ändern fehlgeschlagen', f)
      }
    },
    // F100: einen Pauschalantrag anlegen, ändern, löschen. Jeder trägt seinen
    // Ziel-Typ (Einsparungen / schwarze Null / fester Ertrag / festes Defizit).
    async pauschalErstellen() {
      try {
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/pauschal'), {})
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Pauschalantrag anlegen fehlgeschlagen', f)
      }
    },
    async pauschalAendern(id, felder) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/pauschal/' + id), felder)
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Pauschalantrag ändern fehlgeschlagen', f)
      }
    },
    async pauschalLoeschen(id) {
      try {
        await axios.delete(generateUrl('/apps/parlwin/budget/pauschal/' + id))
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Pauschalantrag löschen fehlgeschlagen', f)
      }
    },
    // F101: kurze Beschriftung eines Pauschalantrags für den Ausnahme-Toggle an der PG.
    pauschalKurz(p) {
      if ((p.zielTyp || p.zielModus) === 'einsparungen') {
        return p.prozent ? 'Pauschalkürzung ' + Math.abs(p.prozent) + '%' : 'Pauschalkürzung ' + this.fr(p.betrag || 0)
      }
      return { schwarze_null: 'Schwarze Null', fester_ertrag: 'Fester Ertrag', festes_defizit: 'Festes Defizit' }[p.zielTyp || p.zielModus] || 'Pauschalantrag'
    },
    // Ist diese Produktegruppe (code) von diesem Pauschalantrag ausgenommen?
    pgAusgenommen(p, code) {
      return Array.isArray(p.ausnahmen) && p.ausnahmen.includes(String(code))
    },
    // F101: die Produktegruppe im Pauschalantrag aufnehmen (teilnehmen=true) oder
    // ausnehmen — trägt den Code in die Ausnahmenliste des Pauschalantrags ein/aus.
    pgPauschalToggle(p, code, teilnehmen) {
      const c = String(code)
      const alt = Array.isArray(p.ausnahmen) ? p.ausnahmen.map(String).filter(x => x !== c) : []
      const neu = teilnehmen ? alt : [...alt, c]
      this.pauschalAendern(p.id, { ausnahmen: neu })
    },
    async entscheidSetzen(id, status) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + id + '/entscheid'), { status })
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Entscheid setzen fehlgeschlagen', f)
      }
    },
    // Haltung als einfacher Toggle (statt Dropdown): eigene «Antrag stellen»
    // (einreichen), fremde «Unterstützen». An = fliesst ins Fraktionsbudget und
    // erscheint in der Übersicht; aus = wird ignoriert.
    haltungAn(a) {
      return a.haltung === 'einreichen' || a.haltung === 'unterstuetzen'
    },
    haltungLabel(a) {
      return a.herkunft === 'fremde' ? 'Unterstützen' : 'Antrag stellen'
    },
    haltungUmschalten(a, wert) {
      const neu = a.herkunft === 'fremde'
        ? (wert ? 'unterstuetzen' : 'nicht_unterstuetzen')
        : (wert ? 'einreichen' : 'nicht_einreichen')
      this.antragHaltung(a.id, neu)
    },
    // Unsere Haltung an einem bestehenden Antrag ändern (F97), getrennt vom
    // Sitzungs-Beschluss.
    async antragHaltung(id, haltung) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + id), { haltung })
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Haltung ändern fehlgeschlagen', f)
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
        this.meldeFehler('Verknüpfung setzen fehlgeschlagen', f)
      }
    },
    // F88: die Automatik pro Jahr am Server umschalten (überlebt einen Reload). Ein
    // ist implizit «Antrag stellen» der eigenen Fraktion; Aus fällt auf den
    // Stadtratsantrag zurück und gibt das manuelle Feld frei. Die Laufsperre
    // verhindert doppelte Auslösung während des (langsamen) Neurechnens.
    async steuerfussAutomatikUmschalten(ein) {
      if (this.steuerfussLaeuft) { return }
      this.steuerfussLaeuft = true
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/steuerfuss-automatik'), { an: !!ein })
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Steuerfuss-Automatik umschalten fehlgeschlagen', f)
      } finally {
        this.steuerfussLaeuft = false
      }
    },
    // Feld-Eingabe (Automatik aus): Wert lokal übernehmen und — wenn «Antrag stellen»
    // ein ist — entprellt in den Antrag schreiben, damit nicht jeder Tastendruck speichert.
    steuerfussManuellSetzen(wert) {
      this.steuerfussManuell = wert
      if (!this.steuerfussAntragStellenAn) { return }
      if (this.steuerfussTimer) { clearTimeout(this.steuerfussTimer) }
      this.steuerfussTimer = setTimeout(() => this.steuerfussAntragSpeichern(), 500)
    },
    // «Antrag stellen»-Toggle (Automatik aus): ein legt den Antrag an bzw. aktualisiert
    // ihn auf den Feldwert, aus löscht ihn (zurück zum Stadtratsantrag).
    async steuerfussAntragStellenUmschalten(ein) {
      if (this.steuerfussLaeuft) { return }
      if (ein) {
        await this.steuerfussAntragSpeichern()
        return
      }
      if (!this.steuerfussAntrag) { return }
      this.steuerfussLaeuft = true
      try {
        await axios.delete(generateUrl('/apps/parlwin/budget/antraege/' + this.steuerfussAntrag.id))
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Steuerfuss-Antrag löschen fehlgeschlagen', f)
      } finally {
        this.steuerfussLaeuft = false
      }
    },
    // Legt den manuellen Steuerfussantrag an oder aktualisiert ihn (F96, Prozentpunkte).
    // Die Laufsperre verhindert doppelte Anträge bei Mehrfachauslösung.
    async steuerfussAntragSpeichern() {
      if (this.steuerfussLaeuft || !this.ansicht || !this.ansicht.jahr) { return }
      const j = this.ansicht.jahr
      const wert = Number(this.steuerfussManuell)
      if (this.steuerfussManuell === '' || !Number.isFinite(wert) || !j.steuerfuss) { return }
      // F96: der Steuerfuss-Antrag wird in Prozentpunkten gestellt; den CHF-Effekt
      // auf den Ertrag rechnet der Server (Ertrag × Prozentpunkte / Steuerfuss).
      const prozentDelta = wert - j.steuerfuss
      this.steuerfussLaeuft = true
      try {
        if (this.steuerfussAntrag) {
          await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + this.steuerfussAntrag.id), { prozentDelta })
        } else {
          await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege'), { bereich: 'steuerfuss', zielTyp: 'steuerfuss', prozentDelta, begruendung: 'Steuerfuss auf ' + wert + '%' })
        }
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Steuerfuss-Antrag fehlgeschlagen', f)
      } finally {
        this.steuerfussLaeuft = false
      }
    },
    // Setzt das manuelle Feld auf den Stand des Servers: den Antragswert, sonst den
    // Stadtratsantrag (Standard). Läuft nach jedem Laden der Ansicht (initEingaben).
    steuerfussManuellSync() {
      if (!this.ansicht || !this.ansicht.jahr) { return }
      const j = this.ansicht.jahr
      this.steuerfussManuell = String(this.steuerfussAntrag ? (j.steuerfuss + (this.steuerfussAntrag.prozentDelta || 0)) : j.steuerfuss)
    },
    // Der scrollende Vorfahr (Nextcloud-App-Inhalt) — die Tabs kleben an dessen
    // oberer Kante, und je Tab wird dessen scrollTop gemerkt/wiederhergestellt.
    scrollContainer() {
      let el = this.$el ? this.$el.parentElement : null
      while (el && el !== document.body) {
        const stil = window.getComputedStyle(el)
        if (/(auto|scroll)/.test(stil.overflowY) && el.scrollHeight > el.clientHeight) { return el }
        el = el.parentElement
      }
      return document.scrollingElement || document.documentElement
    },
    // Tabwechsel mit Scroll-Gedächtnis (nur RAM): Position des alten Tabs merken,
    // Tab umschalten, Position des neuen Tabs wiederherstellen (Standard oben).
    tabWechseln(key) {
      if (key === this.aktiverTab) { return }
      const container = this.scrollContainer()
      if (container) { this.scrollProTab[this.aktiverTab] = container.scrollTop }
      this.aktiverTab = key
      this.$nextTick(() => {
        const c = this.scrollContainer()
        if (c) { c.scrollTop = this.scrollProTab[key] || 0 }
      })
    },
    // Vollbild-Detail einer Produktegruppe öffnen/schliessen (F110).
    pgDetailOeffnen(code) { this.pgDetailCode = code },
    pgDetailSchliessen() { this.pgDetailCode = null },
    /** Was ein Projekt über alle Jahre kostet: bereits getätigt, Budgetjahr, Planjahre. */
    gesamtkosten(i) {
      return Number(i.bereitsGetaetigt || 0) + Number(i.bu || 0)
        + Number(i.fap1 || 0) + Number(i.fap2 || 0) + Number(i.fap3 || 0)
    },
    invDetailOeffnen(id) { this.invDetailId = id },
    invDetailSchliessen() { this.invDetailId = null },
    pdfOeffnen() {
      const params = new URLSearchParams()
      if (this.departementOption) { params.set('kommission', this.departementOption.value) }
      // Option (F92): die von uns unterstützten fremden Anträge mit ins PDF nehmen.
      if (this.pdfMitFremden) { params.set('mitFremden', '1') }
      const q = params.toString()
      window.open(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/antraege-pdf') + (q ? '?' + q : ''), '_blank')
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
        this.meldeFehler('Verfügbare Jahre laden fehlgeschlagen', f)
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
        this.meldeFehler('Budgetjahr importieren fehlgeschlagen', f)
      }
    },
    async novemberbriefEinlesen() {
      try {
        await axios.post(generateUrl('/apps/parlwin/budget/' + this.jahrOption.value + '/novemberbrief'), {})
        await this.ladeAnsicht(false)
      } catch (f) {
        this.meldeFehler('Novemberbrief einlesen fehlgeschlagen', f)
      }
    },
    // Sitzungsanträge (F90) live aus dem Drehbuch der Budgetsitzung einlesen: das
    // Drehbuch wird von der Parlamentswebseite geladen und geparst; die gefundenen
    // Kommissions- und Fraktionsanträge kommen als offizielle Sitzungsanträge dazu.
    async sitzungsantraegeEinlesen() {
      if (!this.jahrOption || this.sitzungsantraegeLaeuft) { return }
      const jahr = this.jahrOption.value
      this.sitzungsantraegeLaeuft = true
      try {
        const { data } = await axios.post(generateUrl('/apps/parlwin/budget/' + jahr + '/sitzungsantraege'), {}, { timeout: 120000 })
        await this.ladeAnsicht(false)
        const neu = (data && data.sitzungsantraegeNeu) || 0
        const gefunden = (data && data.sitzungsantraegeGefunden) || 0
        if (gefunden === 0) {
          showError('Kein Drehbuch zur Budgetsitzung gefunden')
        } else if (neu === 0) {
          showSuccess('Sitzungsanträge sind aktuell — keine neuen Anträge')
        } else {
          showSuccess(neu + ' neue Sitzungsanträge eingelesen')
        }
      } catch (f) {
        this.meldeFehler('Sitzungsanträge einlesen fehlgeschlagen', f)
      } finally {
        this.sitzungsantraegeLaeuft = false
      }
    },
    // Budget neu einlesen — destruktiv, darum doppelt abgesichert (Dialog + Checkbox).
    reimportOeffnen() {
      this.reimportVerstanden = false
      this.reimportOffen = true
    },
    reimportAbbrechen() {
      this.reimportOffen = false
      this.reimportVerstanden = false
    },
    async reimportAusfuehren() {
      if (!this.reimportVerstanden || !this.jahrOption || this.reimportLaeuft) { return }
      const jahr = this.jahrOption.value
      // Dialog sofort schliessen, dann läuft der Fortschrittsbalken; am Ende ein Toast.
      this.reimportOffen = false
      this.reimportVerstanden = false
      this.reimportLaeuft = true
      try {
        // Das Neu-Einlesen parst die Budgetbücher (dauert), darum grosszügiges Timeout.
        await axios.post(generateUrl('/apps/parlwin/budget/' + jahr + '/reimport'), {}, { timeout: 300000 })
        await this.ladeAnsicht(false)
        showSuccess('Budget ' + jahr + ' neu eingelesen')
      } catch (f) {
        this.meldeFehler('Budget neu einlesen fehlgeschlagen', f)
      } finally {
        this.reimportLaeuft = false
      }
    },
  },
}
</script>

<style scoped lang="scss">
/* Nur budget-eigene Elemente (Summenzeile, Tabs, Antragszeilen). View-Rahmen,
   Header, Filter, Karten und Modal stammen aus dem gemeinsamen Stylesheet. */
/* Übersicht und Tabs bilden EINEN Sticky-Block am oberen Rand des scrollenden
   App-Inhalts. Ein einziger Sticky-Container (statt zwei konkurrierende Stickies
   auf top: 0, die sich überlagern) hält beide zusammen: die Übersicht oben, die
   Tabs unten an ihr — ohne Magic-Number für die Höhe der Übersicht. */
.pw-budget-sticky {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--color-main-background);
}
.pw-budget-summen {
  padding: 0.6rem 0.9rem;
  background: var(--color-main-background);
  border-block-end: 2px solid var(--color-border);
  overflow-x: auto;
}
/* Fortschritt beim Neu-Einlesen (F91): indeterminierter Balken (der Server meldet
   keinen Fortschritt), plus Beschriftung. */
.pw-reimport-fortschritt { display: flex; flex-direction: column; gap: 0.35rem; margin-block-end: 0.75rem; }
.pw-reimport-label { font-size: 0.85rem; color: var(--color-text-maxcontrast); }
.pw-reimport-bar { position: relative; block-size: 0.25rem; border-radius: 0.25rem; background: var(--color-background-dark); overflow: hidden; }
.pw-reimport-bar-inner { position: absolute; inset-block: 0; inline-size: 40%; border-radius: 0.25rem; background: var(--color-primary-element); animation: pw-reimport-slide 1.1s ease-in-out infinite; }
@keyframes pw-reimport-slide { 0% { inset-inline-start: -40%; } 100% { inset-inline-start: 100%; } }
.pw-budget-vergleich { inline-size: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.pw-budget-vergleich th, .pw-budget-vergleich td { padding: 0.25rem 0.75rem; text-align: right; white-space: nowrap; }
.pw-budget-vergleich thead th { font-size: 0.8rem; color: var(--color-text-maxcontrast); text-transform: uppercase; letter-spacing: 0.03em; border-block-end: 1px solid var(--color-border); }
.pw-budget-vergleich tbody td { font-size: 1rem; font-weight: 600; }
.pw-budget-vergleich .pw-vergleich-zeile { text-align: left; font-weight: 600; color: var(--color-text-maxcontrast); }
.pw-budget-vergleich .pw-vergleich-differenz td, .pw-budget-vergleich .pw-vergleich-differenz .pw-vergleich-zeile { font-size: 0.85rem; font-weight: 500; border-block-start: 1px solid var(--color-border); }
/* Schmal (Handy): die Übersicht passt als 6-Spalten-Tabelle nicht mehr ins Bild —
   sie bricht in je einen Block pro Budget um (Kennzahl links, Wert rechts), kein
   H-Scroll. Bis ~46rem trägt die Tabelle noch (Tablet), darum erst darunter. */
@media (max-width: 46rem) {
  .pw-budget-summen { overflow-x: visible; }
  .pw-budget-vergleich { display: block; }
  .pw-budget-vergleich thead { display: none; }
  .pw-budget-vergleich tbody, .pw-budget-vergleich tr, .pw-budget-vergleich th, .pw-budget-vergleich td { display: block; }
  .pw-budget-vergleich tr { margin-block-end: 0.7rem; }
  .pw-budget-vergleich .pw-vergleich-zeile { text-align: start; font-size: 0.95rem; padding-block-end: 0.2rem; border-block-end: 1px solid var(--color-border); }
  .pw-budget-vergleich td { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding-block: 0.1rem; }
  .pw-budget-vergleich td::before { content: attr(data-label); font-weight: 400; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.03em; color: var(--color-text-maxcontrast); }
}
.pw-summe { display: flex; flex-direction: column; min-inline-size: 8rem; }
.pw-summe-label { font-size: 0.8rem; color: var(--color-text-maxcontrast); text-transform: uppercase; letter-spacing: 0.03em; }
.pw-summe-wert { font-size: 1.1rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.pw-summe-diff { font-size: 0.8rem; font-variant-numeric: tabular-nums; }
/* Bewusst dunkler als NCs helles --color-success/-error, damit die kleinen
   Delta-Zahlen auf hellem Grund gut lesbar sind. */
/* .pw-positiv/.pw-negativ (+ .pw-diff-plus/-minus) sind global in style.scss
   definiert (einheitliche Farbe für die ganze App) — hier keine eigene Farbe. */

/* Tabs kleben am oberen Rand des scrollenden App-Inhalts, damit man zum Wechseln
   nicht nach oben scrollen muss. Deckender Hintergrund, damit die darunter
   durchlaufenden Karten nicht durchscheinen. */
.pw-budget-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  border-block-end: 1px solid var(--color-border);
  margin-block-end: 0.75rem;
  background: var(--color-main-background);
  padding-block-start: 0.25rem;
}
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
/* Der aktive Tab sieht immer gleich aus — auch nachdem er mit der Maus geklickt
   wurde: kein hängenbleibender Fokus-Hintergrund. Tastatur-Fokus bleibt als Ring
   sichtbar (Barrierefreiheit). */
.pw-budget-tab:focus { background: transparent; box-shadow: none; }
.pw-budget-tab:hover { background: color-mix(in srgb, var(--color-background-hover) 70%, transparent); }
.pw-budget-tab:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: -2px; border-radius: 0.25rem 0.25rem 0 0; }
.pw-budget-tabpanel { display: flex; flex-direction: column; gap: 1rem; }
.pw-budget-dep-titel { font-size: 1.05rem; margin: 0.5rem 0 0.4rem; color: var(--color-primary-element); }
/* Kopf einer Budget-Karte: links Bezeichnung, rechts die Werte. Der Titel darf
   schrumpfen und umbrechen, der Betrag nie — reicht die Breite nicht, rutscht er
   auf eine eigene Zeile, statt am Kartenrand abgeschnitten zu werden. */
.pw-budget-kopf { flex-wrap: wrap; }
.pw-budget-kopf-titel { flex: 1 1 12rem; min-inline-size: 0; }
/* Der Betrag des Budgetjahres ist der Wert, über den entschieden wird: der
   grösste und stärkste der Karte. */
.pw-budget-betrag {
  flex: 0 0 auto;
  /* Die Werte stehen an der rechten Kante der Karte — auch dann, wenn der
     Titel ihnen die Zeile nimmt und sie auf eine eigene rutschen. */
  margin-inline-start: auto;
  display: grid;
  /* Beschriftung, Zahl und Differenz je in ihrer Spalte: so fluchten die Zahlen
     der beiden Zeilen (Stadtrat, Fraktion) auf derselben Kante (F116). */
  grid-template-columns: auto auto auto;
  justify-content: end;
  column-gap: 0.4rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  text-align: end;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}
.pw-budget-wert { display: grid; grid-column: 1 / -1; grid-template-columns: subgrid; align-items: baseline; }
.pw-budget-wert-label {
  font-size: 0.75rem;
  font-weight: 400;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-maxcontrast);
}
.pw-budget-wert-zahl { grid-column: 2; }
.pw-budget-wert .pw-summe-diff { grid-column: 3; }
.pw-budget-antraege { list-style: none; margin: 0.4rem 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-budget-antraege li { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.9rem; }
.pw-antrag-auto { opacity: 0.8; font-style: italic; }
/* F117: Der Antrag ist als anklickbar erkennbar; das Formular darin nimmt die
   ganze Zeile ein. */
.pw-antrag-bearbeitbar { cursor: pointer; border-radius: var(--pw-radius-small, 0.25rem); }
.pw-antrag-bearbeitbar:hover { background: var(--color-background-hover); }
.pw-antrag-bearbeiten { flex: 1 1 100%; cursor: auto; }
.pw-antrag-betrag { font-variant-numeric: tabular-nums; min-inline-size: 6rem; }
.pw-antrag-steller { font-weight: 600; }
.pw-antrag-begruendung { color: var(--color-text-maxcontrast); flex: 1 1 12rem; }
.pw-antrag-entscheid { font-size: 0.8rem; padding: 0.05rem 0.4rem; border-radius: 0.4rem; }
.pw-entscheid-angenommen { background: var(--color-success, #2d7d46); color: var(--color-primary-element-text, #fff); }
.pw-entscheid-abgelehnt { background: var(--color-error, #c0392b); color: var(--color-primary-element-text, #fff); }
.pw-entscheid-offen { background: var(--color-background-dark); }
.pw-entscheid-knoepfe { display: flex; gap: 0.15rem; }
.pw-antrag-haltung { min-inline-size: 10rem; max-inline-size: 14rem; }
.pw-antrag-verknuepfen { min-inline-size: 12rem; max-inline-size: 16rem; }
.pw-antrag-notizen { flex-basis: 100%; font-size: 0.85rem; margin-block-start: 0.2rem; }
.pw-antrag-notizen > summary { cursor: pointer; color: var(--color-text-maxcontrast); }
.pw-verteilung-ziel { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; margin-block-start: 0.4rem; }
/* Künstliche Produktegruppe (F89): abgesetzter Grund, nicht antragbar. */
.pw-budget-kuenstlich { background: var(--pw-surface-kuenstlich); }
.pw-weisung-link { color: var(--color-primary-element); text-decoration: none; font-size: 0.9rem; white-space: nowrap; }
.pw-weisung-link:hover { text-decoration: underline; }
.pw-steuerfuss { display: flex; flex-direction: column; gap: 0.6rem; max-inline-size: 32rem; }
.pw-budget-info { font-size: 0.9rem; color: var(--color-text-maxcontrast); }
.pw-budget-info li { display: flex; justify-content: space-between; gap: 0.5rem; }

/* Weisungs-Link ganz nach rechts im Header (nach den Buttons). */
.pw-weisung-rechts { margin-inline-start: auto; }

/* Anträge-Tab: PDF-Knopf oben rechts, Anträge nach Departement gruppiert. Die
   Titelregel — viel Abstand über der Departement-Überschrift, wenig darunter. */
.pw-antraege-kopf { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: var(--pw-gap-1); margin-block-end: 0.75rem; }

/* EIN Grid über alle Departemente: jede Antragszeile ist ein Subgrid, das die
   Spalten des Container-Grids übernimmt — so fluchten Position, Betrag, Zusatz-
   Zahl, Einheit, Antragsteller, Begründung und Beschluss zeilen- UND gruppen-
   übergreifend. Zahlen rechts, Einheit links auf gemeinsamer Kante (Tabellenregel). */
.pw-antraege-tabelle {
  display: grid;
  grid-template-columns:
    [pos] minmax(8rem, max-content)
    [betrag] max-content
    [znum] max-content
    [zeinheit] max-content
    [steller] max-content
    [grund] minmax(6rem, 1fr)
    [beschluss] max-content;
  column-gap: 0.75rem;
  row-gap: 0.35rem;
  align-items: baseline;
  font-size: 0.9rem;
}
/* Departement-Überschrift über die ganze Breite; Titelregel: viel drüber, wenig drunter. */
.pw-antraege-dep { grid-column: 1 / -1; margin-block: 1.25rem 0.1rem; font-size: 1rem; font-weight: 700; }
.pw-antrag-zeile { grid-column: 1 / -1; display: grid; grid-template-columns: subgrid; align-items: baseline; }
.pw-num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.pw-antrag-znum { min-inline-size: 0; }
.pw-antrag-zeinheit { text-align: start; color: var(--color-text-maxcontrast); }
.pw-antrag-entscheid { justify-self: end; }
/* Schmal: kein horizontales Scrollen — das Grid bricht in gestapelte Antragsblöcke um. */
@media (max-width: 56rem) {
  .pw-antraege-tabelle { display: block; }
  .pw-antrag-zeile { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.1rem 0.5rem; padding-block: 0.35rem; border-block-end: 1px solid var(--color-border); }
  .pw-antrag-pos { flex-basis: 100%; }
  .pw-antrag-begruendung { flex-basis: 100%; }
  .pw-num { text-align: start; }
  /* Im Stapel keine Grid-Mindestbreite und keine leeren Zahl/Einheit-Zellen, sonst
     klafft eine Lücke zwischen Betrag und Antragsteller (z.B. «−3'850   automatisch»). */
  .pw-antrag-betrag { min-inline-size: 0; }
  .pw-antrag-znum:empty, .pw-antrag-zeinheit:empty { display: none; }
}
.pw-produkt-kosten { color: var(--color-text-maxcontrast); white-space: nowrap; }
/* F101: kompakte Liste der Pauschalantrag-Ausnahme-Schalter an der Produktegruppe. */
.pw-pg-pauschale { list-style: none; margin: 0.3rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.15rem; font-size: 0.85rem; }
.pw-modal-aktionen { display: flex; gap: 0.5rem; justify-content: flex-end; padding: 0.75rem 1rem; }
</style>
