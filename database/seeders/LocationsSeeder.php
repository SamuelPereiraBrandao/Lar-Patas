<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = ['AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'];
        $cities = ['SP' => ['São Paulo', 'Campinas', 'Santo André', 'Osasco', 'Guarulhos', 'São Bernardo do Campo', 'Sorocaba'], 'PR' => ['Curitiba', 'Maringá', 'Londrina', 'Ponta Grossa'], 'RJ' => ['Rio de Janeiro', 'Niterói', 'Petrópolis'], 'MG' => ['Belo Horizonte', 'Uberlândia', 'Juiz de Fora'], 'SC' => ['Florianópolis', 'Joinville', 'Blumenau'], 'RS' => ['Porto Alegre', 'Caxias do Sul', 'Pelotas'], 'BA' => ['Salvador', 'Feira de Santana'], 'DF' => ['Brasília'], 'GO' => ['Goiânia'], 'PE' => ['Recife'], 'CE' => ['Fortaleza'], 'AM' => ['Manaus'], 'PA' => ['Belém']];

        $capitals = ['AC' => 'Rio Branco', 'AL' => 'Maceió', 'AP' => 'Macapá', 'AM' => 'Manaus', 'BA' => 'Salvador', 'CE' => 'Fortaleza', 'DF' => 'Brasília', 'ES' => 'Vitória', 'GO' => 'Goiânia', 'MA' => 'São Luís', 'MT' => 'Cuiabá', 'MS' => 'Campo Grande', 'MG' => 'Belo Horizonte', 'PA' => 'Belém', 'PB' => 'João Pessoa', 'PR' => 'Curitiba', 'PE' => 'Recife', 'PI' => 'Teresina', 'RJ' => 'Rio de Janeiro', 'RN' => 'Natal', 'RS' => 'Porto Alegre', 'RO' => 'Porto Velho', 'RR' => 'Boa Vista', 'SC' => 'Florianópolis', 'SP' => 'São Paulo', 'SE' => 'Aracaju', 'TO' => 'Palmas'];

        foreach ($states as $code => $name) {
            $state = State::query()->updateOrCreate(['code' => $code], ['name' => $name]);
            foreach ($cities[$code] ?? [$capitals[$code]] as $city) {
                City::query()->updateOrCreate(['state_id' => $state->id, 'name' => $city]);
            }
        }
    }
}
