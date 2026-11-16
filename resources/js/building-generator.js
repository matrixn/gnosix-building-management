// Pure structure generation shared by the Studio and its regression tests.
export function generateBuilding(state, options, config, strings, uuid = () => crypto.randomUUID()) {
 const fail = () => { throw new Error(strings.too_large); };
 const {levels, rooms, firstLevel = 0, mode = 'replace', individualBeds = false, units = false} = options;
 const preset = config.presets[state.identity.classification];
 if (!preset) throw new Error(strings.choose_preset);
 if (!['replace', 'append'].includes(mode) || !Number.isInteger(levels) || levels < 0 || levels > config.max_levels || !Number.isInteger(rooms) || rooms < 0 || rooms > 500 || !Number.isInteger(firstLevel) || firstLevel < -20 || firstLevel + Math.max(0, levels - 1) > 300) fail();
 const values = {};
 for (const key of ['area_m2', 'bed_count', 'capacity']) {
  const value = options[key];
  values[key] = value === '' || value === null || value === undefined ? null : Number(value);
  if (values[key] !== null && (!Number.isFinite(values[key]) || values[key] < 0 || values[key] > (key === 'area_m2' ? 100000000 : 1000) || (key !== 'area_m2' && !Number.isInteger(values[key])))) fail();
 }
 const result = structuredClone(state);
 if (mode === 'replace') { result.levels = []; result.unplaced_spaces = []; result.units = []; }
 const existing = [...result.levels.flatMap(l => l.spaces), ...result.unplaced_spaces];
 const bedsToCreate = individualBeds ? values.bed_count || 0 : 0;
 const addedUnits = units ? (rooms ? levels * rooms * (bedsToCreate || 1) : 1) : 0;
 if (result.levels.length + levels > config.max_levels || existing.length + levels * rooms > config.max_spaces || existing.reduce((n,s) => n + s.beds.length, 0) + levels * rooms * bedsToCreate > config.max_beds || result.units.length + addedUnits > config.max_units) fail();
 for (let i = 0; i < levels; i++) {
  const floor = firstLevel + i;
  const l = {key: uuid(), name: floor === 0 ? strings.kind_ground : floor < 0 ? strings.kind_basement + ' ' + Math.abs(floor) : strings.level + ' ' + floor, kind: floor === 0 ? 'ground' : floor < 0 ? 'basement' : 'upper', spaces: []};
  for (let j = 0; j < rooms; j++) {
   const number = rooms <= 99 && floor >= 0 ? String(floor) + String(j + 1).padStart(2, '0') : String(floor) + '-' + String(j + 1).padStart(3, '0');
   const type = preset.space_type || 'room';
   const s = {key: uuid(), name: (strings['type_' + type] || strings.space) + ' ' + number, space_type: type, number, ...values, beds: []};
   for (let k = 0; k < bedsToCreate; k++) s.beds.push({key: uuid(), label: strings.bed + ' ' + (k + 1)});
   l.spaces.push(s);
   if (units) {
    if (s.beds.length) s.beds.forEach(b => result.units.push({key: uuid(), name: s.name + ' / ' + b.label, unit_type: 'bed', space_keys: [s.key], bed_key: b.key}));
    else result.units.push({key: uuid(), name: s.name, unit_type: type === 'dormitory' ? 'dormitory' : type === 'apartment' ? 'apartment' : 'room', space_keys: [s.key], bed_key: null});
   }
  }
  result.levels.push(l);
 }
 if (units && !rooms) result.units.push({key: uuid(), name: state.identity.name || strings.new_unit, unit_type: 'whole_building', space_keys: [], bed_key: null});
 return result;
}
