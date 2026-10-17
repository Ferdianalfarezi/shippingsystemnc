# Konfigurasi Auto Print Agent - copy jadi config.psd1 lalu isi.
# Path ditulis biasa (pakai tanda kutip satu), tidak perlu double backslash.
@{
    # Alamat sistem di server
    ServerUrl       = 'http://192.168.87.170:88'

    # Harus sama dengan AUTO_PRINT_TOKEN di .env server
    Token           = 'ISI_SAMA_DENGAN_AUTO_PRINT_TOKEN_DI_ENV_SERVER'

    # Nama printer di PC ini, harus sama persis dengan kolom Printer di halaman schedule
    Printer         = 'SATO Trial Kanban 1'

    EdgePath        = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
    SumatraPath     = 'C:\SumatraPDF\SumatraPDF.exe'
    SumatraSettings = 'fit,landscape'

    # Berapa hari PDF hasil print disimpan
    KeepDays        = 7
}
