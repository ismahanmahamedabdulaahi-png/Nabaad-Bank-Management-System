// Somalia's 18 administrative regions (gobollada) with their major districts/cities.
// Used to power the cascading Country → Region → City selects on customer forms.
export const SOMALIA_REGIONS = {
  'Awdal':          ['Boorama', 'Baki', 'Lughaya', 'Zeila'],
  'Woqooyi Galbeed':['Hargeisa', 'Gabiley', 'Berbera'],
  'Togdheer':       ['Burco', 'Sheikh', 'Owdweyne'],
  'Sanaag':         ['Ceerigaabo', 'Badhan', 'Laasqoray'],
  'Sool':           ['Laascaanood', 'Taleex', 'Xudun'],
  'Bari':           ['Bosaso', 'Qandala', 'Caluula', 'Iskushuban'],
  'Nugaal':         ['Garowe', 'Eyl', 'Burtinle'],
  'Mudug':          ['Galkacyo', 'Hobyo', 'Jariiban'],
  'Galgaduud':      ['Dhusamareb', "Cadaado", 'Cabudwaaq'],
  'Hiiraan':        ['Beledweyne', 'Jalalaqsi', 'Buloburde'],
  'Shabeellaha Dhexe': ['Jowhar', 'Balcad', 'Adale'],
  'Banadir':        ['Mogadishu'],
  'Shabeellaha Hoose': ['Baraawe', 'Merca', 'Qoryooley', 'Afgooye'],
  'Bay':            ['Baidoa', 'Buurhakaba', 'Diinsoor'],
  'Bakool':         ['Xuddur', 'Wajid', 'Rabdhuure'],
  'Gedo':           ['Garbahaarey', 'Luuq', 'Baardheere', 'Ceel Waaq'],
  'Jubbada Dhexe':  ["Bu'aale", 'Saakow', 'Jilib'],
  'Jubbada Hoose':  ['Kismayo', 'Afmadow', 'Badhaadhe'],
}

// Countries offered on the form. Only Somalia has full region/city cascading data —
// any other country falls back to free-text region/city inputs.
export const COUNTRIES = [
  'Somalia', 'Kenya', 'Ethiopia', 'Djibouti',
  'United Arab Emirates', 'United Kingdom', 'United States', 'Other',
]

export const regionsForCountry = (country) =>
  country === 'Somalia' ? Object.keys(SOMALIA_REGIONS) : []

export const citiesForRegion = (country, region) =>
  country === 'Somalia' ? (SOMALIA_REGIONS[region] ?? []) : []
